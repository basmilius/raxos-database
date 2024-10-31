<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use Generator;
use Raxos\Database\Contract\ConnectionInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Logger\EagerLoadEvent;
use Raxos\Database\Orm\{Backbone, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\{BelongsTo, BelongsToMany, BelongsToThrough, HasMany, HasManyThrough, HasOne, HasOneThrough};
use Raxos\Database\Orm\Contract\{CustomRelationAttributeInterface, InitializeInterface, RelationInterface};
use Raxos\Database\Orm\Definition\{ColumnDefinition, PolymorphicDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Relation\{BelongsToManyRelation, BelongsToRelation, BelongsToThroughRelation, HasManyRelation, HasManyThroughRelation, HasOneRelation, HasOneThroughRelation};
use Raxos\Database\Query\Struct\ColumnLiteral;
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
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Structure
 * @since 1.0.17
 */
final class Structure
{

    /** @var ColumnDefinition[]|null */
    public readonly array|null $primaryKey;

    /** @var array<string, RelationInterface> */
    private array $relations = [];

    /**
     * Structure constructor.
     *
     * @param class-string<Model> $class
     * @param ConnectionInterface $connection
     * @param string[]|null $onDuplicateKeyUpdate
     * @param PolymorphicDefinition|null $polymorphic
     * @param PropertyDefinition[] $properties
     * @param string $table
     * @param self|null $parent
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public readonly string $class,
        public readonly ConnectionInterface $connection,
        public readonly ?array $onDuplicateKeyUpdate,
        public readonly ?PolymorphicDefinition $polymorphic,
        public readonly array $properties,
        public readonly string $table,
        public readonly ?self $parent = null
    )
    {
        $this->primaryKey = $this->getPrimaryKey();
    }

    /**
     * Creates a new instance of the model.
     *
     * @param array<string, mixed> $result
     *
     * @return TModel&Model
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function createInstance(array $result): Model
    {
        $cache = $this->connection->cache;
        $primaryKey = $this->primaryKey;

        if (is_array($primaryKey)) {
            $primaryKeyValue = array_map(static fn(ColumnDefinition $property) => $result[$property->key], $primaryKey);
        } else {
            $primaryKeyValue = null;
        }

        if ($primaryKeyValue !== null && $cache->has($this->class, $primaryKeyValue)) {
            $backbone = $cache->get($this->class, $primaryKeyValue)->backbone;

            // note(Bas): If for some reason we fetch a new record, we don't update
            //  the fields but instead keep the existing ones because some of them
            //  may be modified. But we do update internal data points that are used
            //  in relations, for example, such as `__local_linking_key`.
            foreach ($result as $key => $value) {
                if (!str_starts_with($key, '__')) {
                    continue;
                }

                $backbone->data->setValue($key, $value);
            }

            return $backbone->createInstance();
        }

        if ($this->polymorphic !== null) {
            if (!array_key_exists($this->polymorphic->column, $result)) {
                throw StructureException::polymorphicColumnMissing($this->class, $this->polymorphic->column);
            }

            $polymorphicClass = $this->polymorphic->map[$result[$this->polymorphic->column]];
            $polymorphicStructure = StructureGenerator::for($polymorphicClass);

            return $polymorphicStructure->createInstance($result);
        }

        if (is_subclass_of($this->class, InitializeInterface::class)) {
            $result = $this->class::onInitialize($result);
        }

        $backbone = new Backbone($this->class, $result);
        $instance = $backbone->createInstance();

        $cache->set($this->parent?->class ?? $this->class, $primaryKeyValue, $instance);

        return $instance;
    }

    /**
     * Eager loads the given relation for the given model instances.
     *
     * @param RelationInterface $relation
     * @param ModelArrayList $instances
     * @param array $forced
     * @param array $disabled
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function eagerLoadRelation(RelationInterface $relation, ModelArrayList $instances, array $forced = [], array $disabled = []): void
    {
        $property = $relation->property;
        $eagerLoad = $relation->attribute->eagerLoad;

        if ((!$eagerLoad && !$property->isIn($forced)) || $property->isIn($disabled)) {
            return;
        }

        if ($this->connection->logger->isEnabled()) {
            $deferred = $this->connection->logger->deferred();
            $relation->eagerLoad($instances);
            $deferred->commit(new EagerLoadEvent($relation, $this->connection->logger->count() - ($deferred->index + 1)));
        } else {
            $relation->eagerLoad($instances);
        }
    }

    /**
     * Eager loads the relationships of the given instances.
     *
     * @param Model[] $instances
     * @param string[] $forced
     * @param string[] $disabled
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function eagerLoadRelations(array $instances, array $forced = [], array $disabled = []): void
    {
        // note(Bas): if the structure has a parent, which means that the structure
        //  is part of a polymorphic structure, eager load from the parent class.
        if ($this->parent !== null) {
            $this->parent->eagerLoadRelations($instances, $forced, $disabled);

            return;
        }

        // note(Bas): First we eager load all relations from the provided model
        //  class. This ensures that if the model is polymorphic, the common
        //  relations are eaged loaded together.
        $instances = new ModelArrayList($instances);
        $loaded = [];

        foreach ($this->getRelations() as $relation) {
            $this->eagerLoadRelation($relation, $instances, $forced, $disabled);
            $loaded[] = $relation->property->name;
        }

        if ($this->polymorphic === null) {
            return;
        }

        foreach (StructureHelper::groupInstances($instances, $this->class) as $subModelClass => $subInstances) {
            $subStructure = self::of($subModelClass);
            $subRelations = $subStructure->getRelations();

            foreach ($subRelations as $subRelation) {
                if (in_array($subRelation->property->name, $loaded, true)) {
                    continue;
                }

                $this->eagerLoadRelation($subRelation, $subInstances, $forced, $disabled);
            }
        }
    }

    /**
     * Gets the definition of the given property.
     *
     * @param string $key
     *
     * @return PropertyDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getProperty(string $key): PropertyDefinition
    {
        foreach ($this->properties as $property) {
            if ($property->name === $key || ($property instanceof ColumnDefinition && $property->key === $key)) {
                return $property;
            }
        }

        throw StructureException::missingProperty($this->class, $key);
    }

    /**
     * Returns TRUE if a property with the given key exists.
     *
     * @param string $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasProperty(string $key): bool
    {
        foreach ($this->properties as $property) {
            if ($property->name === $key || ($property instanceof ColumnDefinition && $property->key === $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a column literal for the given key.
     *
     * @param string $key
     *
     * @return ColumnLiteral
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getColumn(string $key): ColumnLiteral
    {
        static $cache = [];

        $property = $this->getProperty($key);

        if (!($property instanceof ColumnDefinition)) {
            throw StructureException::invalidColumn($this->class, $key);
        }

        return $cache["{$this->table}:{$key}"] ??= new ColumnLiteral($this->connection->grammar, $property->key, $this->table);
    }

    /**
     * Returns the relation instance for the given property.
     *
     * @param RelationDefinition $property
     *
     * @return RelationInterface
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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
            default => throw StructureException::missingRelationImplementation($this->class, $property->name, $attribute::class)
        };
    }

    /**
     * Generates the relation properties of the model.
     *
     * @return Generator<RelationInterface<Model, Model>>
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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
     * Returns the first primary key as column literal for use in relations.
     *
     * @return ColumnLiteral
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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
     * Gets the primary key(s).
     *
     * @return ColumnDefinition[]|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private function getPrimaryKey(): array|null
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
     * Returns the structure for the given class.
     *
     * @template TStructureModel of Model
     *
     * @param class-string<TStructureModel> $class
     *
     * @return self<TStructureModel>
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function of(string $class): self
    {
        return StructureGenerator::for($class);
    }

}