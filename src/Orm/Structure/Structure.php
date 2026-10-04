<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use Generator;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\Orm\BackboneInitializedInterface;
use Raxos\Contract\Database\Orm\CustomRelationAttributeInterface;
use Raxos\Contract\Database\Orm\InitializeInterface;
use Raxos\Contract\Database\Orm\RelationInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\SerializableInterface;
use Raxos\Database\Db;
use Raxos\Database\Logger\EagerLoadEvent;
use Raxos\Database\Orm\Attribute\BelongsTo;
use Raxos\Database\Orm\Attribute\BelongsToMany;
use Raxos\Database\Orm\Attribute\BelongsToThrough;
use Raxos\Database\Orm\Attribute\HasMany;
use Raxos\Database\Orm\Attribute\HasManyThrough;
use Raxos\Database\Orm\Attribute\HasOne;
use Raxos\Database\Orm\Attribute\HasOneThrough;
use Raxos\Database\Orm\Backbone;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Orm\Definition\PolymorphicDefinition;
use Raxos\Database\Orm\Definition\PropertyDefinition;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\InvalidColumnException;
use Raxos\Database\Orm\Error\MissingPolymorphicDiscriminatorException;
use Raxos\Database\Orm\Error\MissingPropertyException;
use Raxos\Database\Orm\Error\MissingRelationImplementationException;
use Raxos\Database\Orm\Error\ReflectionErrorException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\BelongsToManyRelation;
use Raxos\Database\Orm\Relation\BelongsToRelation;
use Raxos\Database\Orm\Relation\BelongsToThroughRelation;
use Raxos\Database\Orm\Relation\HasManyRelation;
use Raxos\Database\Orm\Relation\HasManyThroughRelation;
use Raxos\Database\Orm\Relation\HasOneRelation;
use Raxos\Database\Orm\Relation\HasOneThroughRelation;
use Raxos\Database\Query\Expression\ColumnRef;
use ReflectionClass;
use ReflectionException;
use function array_key_exists;
use function array_map;
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

    private const bool ENABLE_LAZY_GHOST = false;

    /**
     * Retains the connection used by this object for its entire lifetime.
     *
     * @var ConnectionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public private(set) ConnectionInterface $connection;

    /**
     * Identifies the property used to address one model row and maintain its cached identity.
     *
     * @var ColumnDefinition[]|null
     */
    public readonly array|null $primaryKey;

    /**
     * Records whether hydration can initialize this model directly.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public bool $isInitializable = false;

    /**
     * Records whether shared model state can be initialized by the generated constructor.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public bool $isBackboneInitializable = false;

    /**
     * Retains relation attributes until relation objects can safely resolve their related structures.
     *
     * @var RelationDefinition[]
     */
    public array $relationDefinitions = [];

    /**
     * Preserves the property order used for generated model access.
     *
     * @var string[]
     */
    public array $propertyNames = [];

    /**
     * Maps property names to stable positions in the generated access metadata.
     *
     * @var array<string, PropertyDefinition>
     */
    private array $propertyIndex = [];

    /**
     * Caches instantiated relations after their related structures have become available.
     *
     * @var array<string, RelationInterface>
     */
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

        $this->buildPropertyIndex();

        $this->isInitializable = is_subclass_of($this->class, InitializeInterface::class);
        $this->isBackboneInitializable = is_subclass_of($this->class, BackboneInitializedInterface::class);
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

            $discriminatorValue = $data[$this->polymorphic->column];
            $polymorphicClass = $this->polymorphic->map[$discriminatorValue] ?? throw new MissingPolymorphicDiscriminatorException($this->class, $this->polymorphic->column);
            $polymorphicStructure = StructureGenerator::for($polymorphicClass);

            return $polymorphicStructure->createInstance($data);
        }

        if ($this->isInitializable) {
            $data = $this->class::onInitialize($data);
        }

        if (self::ENABLE_LAZY_GHOST) {
            $class = $this->class;
            $parent = $this->parent;

            try {
                /** @var TModel $instance */
                $instance = new ReflectionClass($class)->newLazyGhost(function (Model $instance) use ($class, $data): void {
                    if (is_subclass_of($class, InitializeInterface::class)) {
                        $data = $class::onInitialize($data);
                    }

                    $backbone = new Backbone($this, $data);

                    if ($this->isBackboneInitializable) {
                        $class::onBackboneInitialized($backbone, $data);
                    }

                    $instance->__construct(backbone: $backbone);
                });

                $cache->set($parent?->class ?? $class, $primaryKeyValue, $instance);

                return $instance;
            } catch (ReflectionException $err) {
                throw new ReflectionErrorException($this->class, $err);
            }
        } else {
            $backbone = new Backbone($this, $data);

            if ($this->isBackboneInitializable) {
                $this->class::onBackboneInitialized($backbone, $data);
            }

            $instance = $backbone->createInstance();

            $cache->set($this->parent?->class ?? $this->class, $primaryKeyValue, $instance);

            return $instance;
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eagerLoadRelation(
        RelationInterface $relation,
        ArrayListInterface $instances
    ): void
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
    public function eagerLoadRelations(
        ArrayListInterface $instances,
        array $enabled = [],
        array $disabled = []
    ): void
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
            $loaded[$relation->property->name] = true;
        }

        if ($this->polymorphic === null) {
            return;
        }

        foreach (StructureHelper::groupInstances($instances, $this->class) as $subModelClass => $subInstances) {
            $subStructure = StructureGenerator::for($subModelClass);
            $subRelations = $subStructure->getRelations();

            foreach ($subRelations as $subRelation) {
                if (isset($loaded[$subRelation->property->name])) {
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
        return $this->propertyIndex[$key] ?? throw new MissingPropertyException($this->class, $key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function hasProperty(string $key): bool
    {
        return isset($this->propertyIndex[$key]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getColumn(
        string $key,
        ?string $table = null
    ): ColumnRef
    {
        static $cache = [];

        $property = $this->getProperty($key);
        $table ??= $this->table;

        if (!($property instanceof ColumnDefinition)) {
            throw new InvalidColumnException($this->class, $key);
        }

        return $cache["{$table}:{$property->key}"] ??= new ColumnRef($property->key, $table);
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
        foreach ($this->relationDefinitions as $property) {
            yield self::getRelation($property);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelationPrimaryKey(): ColumnRef
    {
        static $cache = [];

        $property = $this->primaryKey;

        if (is_array($property)) {
            $property = $property[0];
        }

        return $cache["{$this->table}:{$property->key}"] ??= new ColumnRef($property->key, $this->table);
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
     * Builds the property index for O(1) property lookups by name, alias, and column key.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.1
     */
    private function buildPropertyIndex(): void
    {
        foreach ($this->properties as $property) {
            $this->propertyNames[] = $property->name;
            $this->propertyIndex[$property->name] = $property;

            if ($property->alias !== null) {
                $this->propertyIndex[$property->alias] = $property;
            }

            if ($property instanceof ColumnDefinition && $property->key !== $property->name) {
                $this->propertyIndex[$property->key] = $property;
            }

            if ($property instanceof RelationDefinition) {
                $this->relationDefinitions[] = $property;
            }
        }
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

        $this->buildPropertyIndex();

        $this->isInitializable = is_subclass_of($this->class, InitializeInterface::class);
        $this->isBackboneInitializable = is_subclass_of($this->class, BackboneInitializedInterface::class);
    }
}
