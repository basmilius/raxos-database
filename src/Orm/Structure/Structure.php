<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use Generator;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface};
use Raxos\Contract\Database\Orm\{BackboneInitializedInterface, CustomRelationAttributeInterface, InitializeInterface, RelationInterface, StructureInterface};
use Raxos\Contract\SerializableInterface;
use Raxos\Database\Db;
use Raxos\Database\Logger\EagerLoadEvent;
use Raxos\Database\Orm\{Backbone, Error\MissingPolymorphicDiscriminatorException, Error\MissingPropertyException, Error\MissingRelationImplementationException, Model};
use Raxos\Database\Orm\Attribute\{BelongsTo, BelongsToMany, BelongsToThrough, HasMany, HasManyThrough, HasOne, HasOneThrough};
use Raxos\Database\Orm\Definition\{ColumnDefinition, PolymorphicDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\InvalidColumnException;
use Raxos\Database\Orm\Relation\{BelongsToManyRelation, BelongsToRelation, BelongsToThroughRelation, HasManyRelation, HasManyThroughRelation, HasOneRelation, HasOneThroughRelation};
use Raxos\Database\Query\Literal\ColumnLiteral;
use function array_any;
use function array_key_exists;
use function array_map;
use function in_array;
use function is_array;
use function is_subclass_of;
use function str_starts_with;

/**
 * Class Structure
 *
 * @template TModel of Model
 * @implements StructureInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Structure
 * @since 1.0.17
 */
final class Structure implements StructureInterface, SerializableInterface
{

    public private(set) ConnectionInterface $connection;

    /** @var ColumnDefinition[]|null */
    public readonly array|null $primaryKey;

    /** @var array<string, RelationInterface> */
    private array $relations = [];

    /**
     * Structure constructor.
     *
     * @param class-string<Model> $class
     * @param string $connectionId
     * @param string[]|null $onDuplicateKeyUpdate
     * @param PolymorphicDefinition|null $polymorphic
     * @param PropertyDefinition[] $properties
     * @param string|null $softDeleteColumn
     * @param string $table
     * @param StructureInterface|null $parent
     *
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public readonly string $class,
        public readonly string $connectionId,
        public readonly ?array $onDuplicateKeyUpdate,
        public readonly ?PolymorphicDefinition $polymorphic,
        public readonly array $properties,
        public readonly ?string $softDeleteColumn,
        public readonly string $table,
        public readonly ?StructureInterface $parent = null
    )
    {
        $this->connection = Db::getOrFail($this->connectionId);
        $this->primaryKey = $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function createInstance(array $data): Model
    {
        $cache = $this->connection->cache;
        $primaryKey = $this->primaryKey;

        if (is_array($primaryKey)) {
            $primaryKeyValue = array_map(static fn(ColumnDefinition $property) => $data[$property->key], $primaryKey);
        } else {
            $primaryKeyValue = null;
        }

        if ($primaryKeyValue !== null && $cache->has($this->class, $primaryKeyValue)) {
            $backbone = $cache->get($this->class, $primaryKeyValue)->backbone;

            // note(Bas): If for some reason we fetch a new record, we don't update
            //  the fields but instead keep the existing ones because some of them
            //  may be modified. But we do update internal data points that are used
            //  in relations, for example, such as `__local_linking_key`.
            foreach ($data as $key => $value) {
                if (!str_starts_with($key, '__')) {
                    continue;
                }

                $backbone->data->setValue($key, $value);
            }

            return $backbone->createInstance();
        }

        if ($this->polymorphic !== null) {
            if (!array_key_exists($this->polymorphic->column, $data)) {
                throw new MissingPolymorphicDiscriminatorException($this->class, $this->polymorphic->column);
            }

            $polymorphicClass = $this->polymorphic->map[$data[$this->polymorphic->column]];
            $polymorphicStructure = StructureGenerator::for($polymorphicClass);

            return $polymorphicStructure->createInstance($data);
        }

        if (is_subclass_of($this->class, InitializeInterface::class)) {
            $data = $this->class::onInitialize($data);
        }

        $structure = StructureGenerator::for($this->class);
        $backbone = new Backbone($structure, $data);

        if (is_subclass_of($this->class, BackboneInitializedInterface::class)) {
            $this->class::onBackboneInitialized($backbone, $data);
        }

        $instance = $backbone->createInstance();

        $cache->set($this->parent?->class ?? $this->class, $primaryKeyValue, $instance);

        return $instance;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eagerLoadRelation(RelationInterface $relation, ArrayListInterface $instances): void
    {
        if ($this->connection->logger->enabled) {
            $deferred = $this->connection->logger->deferred();
            $relation->eagerLoad($instances);
            $deferred->commit(new EagerLoadEvent($relation, $this->connection->logger->count() - ($deferred->index + 1)));
        } else {
            $relation->eagerLoad($instances);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eagerLoadRelations(ArrayListInterface $instances, array $enabled = [], array $disabled = []): void
    {
        // note(Bas): if the structure has a parent, which means that the structure
        //  is part of a polymorphic structure, eager load from the parent class.
        if ($this->parent !== null) {
            $this->parent->eagerLoadRelations($instances, $enabled, $disabled);

            return;
        }

        // note(Bas): First we eager load all relations from the provided model
        //  class. This ensures that if the model is polymorphic, the common
        //  relations are eaged loaded together.
        $loaded = [];

        foreach ($this->getRelations() as $relation) {
            if ((!$relation->attribute->eagerLoad && !$relation->property->isIn($enabled)) || $relation->property->isIn($disabled)) {
                continue;
            }

            $this->eagerLoadRelation($relation, $instances);
            $loaded[] = $relation->property->name;
        }

        if ($this->polymorphic === null) {
            return;
        }

        foreach (StructureHelper::groupInstances($instances, $this->class) as $subModelClass => $subInstances) {
            $subStructure = StructureGenerator::for($subModelClass);
            $subRelations = $subStructure->getRelations();

            foreach ($subRelations as $subRelation) {
                if (in_array($subRelation->property->name, $loaded, true)) {
                    continue;
                }

                if ((!$subRelation->attribute->eagerLoad && !$subRelation->property->isIn($enabled)) || $subRelation->property->isIn($disabled)) {
                    continue;
                }

                $this->eagerLoadRelation($subRelation, $subInstances);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getProperty(string $key): PropertyDefinition
    {
        foreach ($this->properties as $property) {
            if ($property->name === $key || $property->alias === $key || ($property instanceof ColumnDefinition && $property->key === $key)) {
                return $property;
            }
        }

        throw new MissingPropertyException($this->class, $key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function hasProperty(string $key): bool
    {
        return array_any($this->properties, static fn(PropertyDefinition $property) => $property->name === $key || $property->alias === $key || ($property instanceof ColumnDefinition && $property->key === $key));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getColumn(string $key, ?string $table = null): ColumnLiteral
    {
        static $cache = [];

        $property = $this->getProperty($key);
        $table ??= $this->table;

        if (!($property instanceof ColumnDefinition)) {
            throw new InvalidColumnException($this->class, $key);
        }

        return $cache["{$table}:{$key}"] ??= new ColumnLiteral($this->connection->grammar, $property->key, $table);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelation(RelationDefinition $property): RelationInterface
    {
        $attribute = $property->relation;

        return $this->relations[$property->name] ??= match (true) {
            $attribute instanceof BelongsTo => new BelongsToRelation($attribute, $property, $this),
            $attribute instanceof BelongsToMany => new BelongsToManyRelation($attribute, $property, $this),
            $attribute instanceof BelongsToThrough => new BelongsToThroughRelation($attribute, $property, $this),
            $attribute instanceof HasMany => new HasManyRelation($attribute, $property, $this),
            $attribute instanceof HasManyThrough => new HasManyThroughRelation($attribute, $property, $this),
            $attribute instanceof HasOne => new HasOneRelation($attribute, $property, $this),
            $attribute instanceof HasOneThrough => new HasOneThroughRelation($attribute, $property, $this),
            $attribute instanceof CustomRelationAttributeInterface => $attribute->createRelationInstance($property, $this),
            default => throw new MissingRelationImplementationException($this->class, $property->name, $attribute::class)
        };
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelations(): Generator
    {
        foreach ($this->properties as $property) {
            if ($property instanceof RelationDefinition) {
                yield self::getRelation($property);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelationPrimaryKey(): ColumnLiteral
    {
        static $cache = [];

        $property = $this->primaryKey;

        if (is_array($property)) {
            $property = $property[0];
        }

        return $cache["{$this->table}:{$property->key}"] ??= new ColumnLiteral($this->connection->grammar, $property->key, $this->table);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getPrimaryKey(): array|null
    {
        $properties = [];

        foreach ($this->properties as $property) {
            if (!($property instanceof ColumnDefinition)) {
                continue;
            }

            if (!$property->isPrimaryKey) {
                continue;
            }

            $properties[] = $property;
        }

        if (empty($properties)) {
            return null;
        }

        return $properties;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __serialize(): array
    {
        return [
            $this->class,
            $this->connectionId,
            $this->onDuplicateKeyUpdate,
            $this->polymorphic,
            $this->properties,
            $this->softDeleteColumn,
            $this->table,
            $this->parent?->class
        ];
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __unserialize(array $data): void
    {
        [
            $this->class,
            $this->connectionId,
            $this->onDuplicateKeyUpdate,
            $this->polymorphic,
            $this->properties,
            $this->softDeleteColumn,
            $this->table,
            $parentClass
        ] = $data;

        $this->connection = Db::getOrFail($this->connectionId);
        $this->parent = $parentClass !== null ? StructureGenerator::for($parentClass) : null;
        $this->primaryKey = $this->getPrimaryKey();
    }

}
