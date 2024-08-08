<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Generator;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Error\{DatabaseException, ModelException};
use Raxos\Database\Logger\EagerLoadEvent;
use Raxos\Database\Orm\Attribute\{Alias, AttributeInterface, BelongsTo, BelongsToMany, Caster, Column, ConnectionId, CustomRelationInterface, ForeignKey, HasMany, HasManyThrough, HasOne, Hidden, Immutable, Macro, Polymorphic, PrimaryKey, RelationAttributeInterface, Table, Visible};
use Raxos\Database\Orm\Cast\{BooleanCast, CastInterface, ModelAwareCastInterface};
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition};
use Raxos\Database\Orm\Relation\{BelongsToManyRelation, BelongsToRelation, HasManyRelation, HasManyThroughRelation, HasOneRelation, RelationInterface};
use Raxos\Foundation\Collection\ArrayList;
use Raxos\Foundation\Util\{ArrayUtil, ReflectionUtil, Singleton, Stopwatch};
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use WeakMap;
use function array_map;
use function class_exists;
use function count;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function is_subclass_of;
use function sprintf;

/**
 * Class InternalModelData
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.16
 * @internal
 * @private
 */
final class InternalModelData
{

    /** @var array<class-string<Model>, CastInterface|null> */
    public static array $casters = [];

    /** @var array<class-string<Model>, string> */
    public static array $connectionId = [];

    /** @var array<class-string<Model>, (ColumnDefinition|MacroDefinition)[]> */
    public static array $fields = [];

    /** @var array<class-string<Model>, bool> */
    public static array $initialized = [];

    /** @var array<class-string<Model>, string> */
    public static array $polymorphicColumn = [];

    /** @var array<class-string<Model>, array<string, class-string<Model>>> */
    public static array $polymorphicMap = [];

    /** @var WeakMap<RelationInterface, WeakMap<Model, Model>> */
    public static WeakMap $relationCache;

    /** @var array<class-string<Model>, array<string, RelationInterface<Model, Model>>> */
    public static array $relations = [];

    /** @var array<class-string<Model>, string> */
    public static array $table = [];

    /**
     * Casts the given value using the given caster class.
     *
     * @param class-string<CastInterface> $casterClass
     * @param string $mode
     * @param mixed $value
     * @param Model $model
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    #[Pure]
    public static function cast(string $casterClass, #[ExpectedValues(['decode', 'encode'])] string $mode, mixed $value, Model $model): mixed
    {
        $caster = self::$casters[$casterClass] ??= Singleton::get($casterClass);

        if ($caster instanceof ModelAwareCastInterface) {
            return $caster->{$mode}($value, $model);
        }

        return $caster->{$mode}($value);
    }

    /**
     * Gets the defined columns for the given model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     *
     * @return Generator<ColumnDefinition>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getColumns(string $modelClass): Generator
    {
        foreach (self::$fields[$modelClass] ?? [] as $def) {
            if ($def instanceof ColumnDefinition) {
                yield $def;
            }
        }
    }

    /**
     * Gets a defined field of the given model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param string $key
     *
     * @return ColumnDefinition|MacroDefinition|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getField(string $modelClass, string $key): ColumnDefinition|MacroDefinition|null
    {
        return self::$fields[$modelClass][$key] ?? ArrayUtil::first(self::$fields[$modelClass], static fn(ColumnDefinition|MacroDefinition $def) => $def->key === $key);
    }

    /**
     * Gets the defined fields of the given model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     *
     * @return Generator<ColumnDefinition|MacroDefinition>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getFields(string $modelClass): Generator
    {
        foreach (self::$fields[$modelClass] ?? [] as $def) {
            yield $def;
        }
    }

    /**
     * Gets the defined macros for the given model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     *
     * @return Generator<MacroDefinition>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getMacros(string $modelClass): Generator
    {
        foreach (self::$fields[$modelClass] ?? [] as $def) {
            if ($def instanceof MacroDefinition) {
                yield $def;
            }
        }
    }

    /**
     * Gets the relation instance for the given model and column.
     *
     * @template TDeclaringModel of Model
     * @template TReferenceModel of Model
     *
     * @param class-string<TDeclaringModel> $modelClass
     * @param ColumnDefinition $def
     *
     * @return RelationInterface<TDeclaringModel, TReferenceModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getRelation(string $modelClass, ColumnDefinition $def): RelationInterface
    {
        if ($def->relation === null) {
            throw new ModelException(sprintf('Model %s does not have a relation named %s.', $modelClass, $def->name), ModelException::ERR_RELATION_NOT_FOUND);
        }

        $relation = $def->relation;

        self::$relations[$modelClass] ??= [];

        return self::$relations[$modelClass][$def->key] ??= match (true) {
            $relation instanceof BelongsTo => new BelongsToRelation($relation, $def, $modelClass),
            $relation instanceof BelongsToMany => new BelongsToManyRelation($relation, $def, $modelClass),
            $relation instanceof HasOne => new HasOneRelation($relation, $def, $modelClass),
            $relation instanceof HasMany => new HasManyRelation($relation, $def, $modelClass),
            $relation instanceof HasManyThrough => new HasManyThroughRelation($relation, $def, $modelClass),
            $relation instanceof CustomRelationInterface => $relation->createRelationInstance($relation, $def, $modelClass),
            default => throw new ModelException(sprintf('Could not find a relation implementation for %s for field %s in model %s.', $def->relation::class, $def->name, $modelClass), ModelException::ERR_RELATION_NOT_FOUND)
        };
    }

    /**
     * Gets the defined relations for the given model.
     *
     * @template TDeclaringModel of Model
     * @template TReferenceModel of Model
     *
     * @param class-string<TDeclaringModel> $modelClass
     *
     * @return Generator<RelationInterface<TDeclaringModel, TReferenceModel>>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getRelations(string $modelClass): Generator
    {
        foreach (self::getColumns($modelClass) as $def) {
            if ($def->relation !== null) {
                yield self::getRelation($modelClass, $def);
            }
        }
    }

    /**
     * Returns TRUE if the given field is a relation.
     *
     * @param ColumnDefinition|MacroDefinition|null $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    #[Pure]
    public static function isRelation(ColumnDefinition|MacroDefinition|null $field): bool
    {
        return $field instanceof ColumnDefinition && $field->relation !== null;
    }

    /**
     * Copies over settings from the given master model to the given model.
     *
     * @template TMasterModel of Model
     * @template TModel of TMasterModel
     *
     * @param class-string<TModel> $modelClass
     * @param class-string<TMasterModel> $masterModelClass
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function copySettings(string $modelClass, string $masterModelClass): void
    {
        self::$table[$modelClass] = self::$table[$masterModelClass] ?? null;
    }

    /**
     * Restores the settings of the model from the given settings.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param array $modelSettings
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function restoreSettings(string $modelClass, array $modelSettings): void
    {
        [
            self::$fields[$modelClass],
            self::$polymorphicColumn[$modelClass],
            self::$polymorphicMap[$modelClass],
            self::$table[$modelClass]
        ] = $modelSettings;

        self::$initialized[$modelClass] = true;
    }

    /**
     * Returns the settings of the model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     *
     * @return array
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function saveSettings(string $modelClass): array
    {
        self::initialize($modelClass);

        return [
            self::$fields[$modelClass],
            self::$polymorphicColumn[$modelClass],
            self::$polymorphicMap[$modelClass],
            self::$table[$modelClass]
        ];
    }

    /**
     * Initializes the model.
     *
     * @param string $modelClass
     *
     * @return void
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function initialize(string $modelClass): void
    {
        if (isset(self::$initialized[$modelClass])) {
            return;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new ModelException(sprintf('Referenced model %s is not a model.', $modelClass), ModelException::ERR_NOT_A_MODEL);
        }

        self::$fields[$modelClass] = [];
        self::$initialized[$modelClass] = true;

        try {
            $class = new ReflectionClass($modelClass);
            $attributes = $class->getAttributes();

            foreach ($attributes as $attribute) {
                if (!is_subclass_of($attribute->getName(), AttributeInterface::class)) {
                    continue;
                }

                $attr = $attribute->newInstance();

                switch (true) {
                    case $attr instanceof ConnectionId:
                        self::$connectionId[$modelClass] = $attr->connectionId;
                        break;

                    case $attr instanceof Polymorphic:
                        self::$polymorphicColumn[$modelClass] = $attr->column;
                        self::$polymorphicMap[$modelClass] = $attr->map;
                        break;

                    case $attr instanceof Table:
                        $name = $attr->name;

                        if ($name === null) {
                            $name = $class->getShortName();
                        }

                        self::$table[$modelClass] = $name;
                        break;

                    default:
                        continue 2;
                }
            }

            // note: This makes models based on another model possible.
            if (($parentClass = $class->getParentClass())->name !== Model::class) {
                /** @var class-string<Model> $parentModel */
                $parentModel = $parentClass->name;
                self::initialize($parentModel);
                self::copySettings($modelClass, $parentModel);
                self::initializeFields($modelClass, $parentClass);
            }

            self::initializeFields($modelClass, $class);
        } catch (ReflectionException $err) {
            throw new ModelException($err->getMessage(), ModelException::ERR_REFLECTION_FAILED);
        }
    }

    /**
     * Initializes the fields of the model, based on the properties
     * of the model class.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param ReflectionClass $class
     *
     * @return void
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function initializeFields(string $modelClass, ReflectionClass $class): void
    {
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            if ($property->class !== $class->name) {
                continue;
            }

            $attributes = $property->getAttributes();

            if (ArrayUtil::some($attributes, static fn(ReflectionAttribute $attr) => $attr->getName() === Macro::class)) {
                self::initializeMacro($modelClass, $property, $attributes);
            } else {
                self::initializeColumn($modelClass, $property, $attributes);
            }
        }
    }

    /**
     * Initializes a single field of the model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param ReflectionProperty $property
     * @param ReflectionAttribute[] $attributes
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function initializeColumn(string $modelClass, ReflectionProperty $property, array $attributes): void
    {
        $alias = null;
        $cast = null;
        $default = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
        $isImmutable = false;
        $isPrimary = false;
        $isForeign = false;
        $isHidden = false;
        $isVisible = false;
        $key = $property->name;
        $relation = null;
        $types = ReflectionUtil::getTypes($property->getType()) ?? [];
        $validColumn = false;
        $hiddenOnly = null;
        $visibleOnly = null;

        foreach ($attributes as $attribute) {
            if (!is_subclass_of($attribute->getName(), AttributeInterface::class)) {
                continue;
            }

            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    break;

                case $attr instanceof Caster:
                    if (!isset(self::$casters[$attr->caster])) {

                        if (!class_exists($attr->caster)) {
                            throw new ModelException(sprintf('Caster "%s" not found.', $attr->caster), ModelException::ERR_CASTER_NOT_FOUND);
                        }

                        if (!is_subclass_of($attr->caster, CastInterface::class)) {
                            throw new ModelException(sprintf('Class "%s" is not a valid caster class.', $attr->caster), ModelException::ERR_CASTER_NOT_FOUND);
                        }

                        self::$casters[$attr->caster] = null;
                    }

                    $cast = $attr->caster;
                    break;

                case $attr instanceof Column:
                    $validColumn = true;
                    $isForeign = $attr instanceof ForeignKey;
                    $isImmutable = $attr instanceof PrimaryKey;
                    $isPrimary = $attr instanceof PrimaryKey;
                    $key = $attr->key ?? $property->name;
                    break;

                case $attr instanceof RelationAttributeInterface:
                    $relation = $attr;
                    $validColumn = true;
                    break;

                case $attr instanceof Immutable:
                    $isImmutable = true;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    $hiddenOnly = is_string($attr->only) ? [$attr->only] : $attr->only;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;
                    $visibleOnly = is_string($attr->only) ? [$attr->only] : $attr->only;
                    break;

                default:
                    continue 2;
            }
        }

        if (!$validColumn) {
            return;
        }

        if ($cast === null && count($types) === 1 && $types[0] === 'bool') {
            self::$casters[BooleanCast::class] ??= null;
            $cast = BooleanCast::class;
        }

        self::$fields[$modelClass][$property->name] = new ColumnDefinition(
            $alias,
            $cast,
            $default,
            $isImmutable,
            $isPrimary,
            $isForeign,
            $isHidden,
            $isVisible,
            $property->name,
            $key,
            $relation,
            $types,
            $hiddenOnly,
            $visibleOnly
        );
    }

    /**
     * Initializes a macro of the model.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param ReflectionProperty $property
     * @param ReflectionAttribute[] $attributes
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function initializeMacro(string $modelClass, ReflectionProperty $property, array $attributes): void
    {
        $alias = null;
        $callable = null;
        $isCacheable = false;
        $isHidden = false;
        $isVisible = false;

        foreach ($attributes as $attribute) {
            if (!is_subclass_of($attribute->getName(), AttributeInterface::class)) {
                continue;
            }

            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    break;

                case $attr instanceof Macro:
                    $isCacheable = $attr->cached;
                    $callable = $attr->implementation;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;
                    break;
            }
        }

        if (!is_callable($callable)) {
            throw new ModelException(sprintf('Macro %s in model %s is missing its callable.', $property->name, $property->class), ModelException::ERR_MACRO_METHOD_NOT_FOUND);
        }

        self::$fields[$modelClass][$property->name] = new MacroDefinition(
            $property->name,
            $alias,
            $isCacheable,
            $isHidden,
            $isVisible,
            $callable
        );
    }

    /**
     * Creates a new instance of the current model class with the given
     * column attributes.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param mixed $result
     * @param string|null $masterModel
     *
     * @return TModel&Model
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function createInstance(string $modelClass, mixed $result, string $masterModel = null): Model
    {
        if ($masterModel !== null) {
            self::copySettings($modelClass, $masterModel);
        }

        if (($typeColumn = self::$polymorphicColumn[$modelClass] ?? null) !== null) {
            /** @var class-string<Model> $polymorphicClassName */
            $polymorphicClassName = self::$polymorphicMap[$modelClass][$result[$typeColumn]] ?? null;

            if ($polymorphicClassName !== null) {
                return self::createInstance($polymorphicClassName, $result, $modelClass);
            }
        }

        $cache = $modelClass::cache();
        $primaryKey = $modelClass::getPrimaryKey();

        if (is_array($primaryKey)) {
            $primaryKeyValue = array_map(static fn(string $key) => $result[$key], $primaryKey);
        } else if (!empty($primaryKey)) {
            $primaryKeyValue = $result[$primaryKey];
        } else {
            $primaryKeyValue = null;
        }

        if ($primaryKeyValue !== null && $cache->has($modelClass, $primaryKeyValue)) {
            return $cache->get($modelClass, $primaryKeyValue);
        }

        $instance = new $modelClass($result, false);
        $cache->set($instance, $masterModel);

        return $instance;
    }

    /**
     * Eager loads the relationships of the given models.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $modelClass
     * @param Model[] $instances
     * @param string[] $forceEagerLoad
     * @param string[] $eagerLoadDisable
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function eagerLoadRelationships(string $modelClass, array $instances, array $forceEagerLoad = [], array $eagerLoadDisable = []): void
    {
        $connection = $modelClass::connection();
        $relations = self::getRelations($modelClass);

        foreach ($relations as $relation) {
            $column = $relation->column->name;
            $eagerLoad = $relation->attribute->eagerLoad;

            if ((!$eagerLoad && !in_array($column, $forceEagerLoad)) || in_array($column, $eagerLoadDisable)) {
                continue;
            }

            if ($connection->logger->isEnabled()) {
                $deferred = $connection->logger->deferred();
                $stopwatch = new Stopwatch(__METHOD__);
                $stopwatch->run(fn() => $relation->eagerLoad(new ArrayList($instances)));

                $deferred->commit(new EagerLoadEvent($relation, $stopwatch));
            } else {
                $relation->eagerLoad(new ArrayList($instances));
            }
        }

//        return;
//
//        $relations = InternalModelData::getRelations($modelClass);
//        $didRelations = [];
//
//        // note: first we need to determine which relations are in the current model,
//        //  for polymorphic relations it's more performant to combine the relations
//        //  of the underlying types into one big query.
//        foreach ($relations as $relation) {
//            $fieldName = $relation->fieldName;
//
//            if ((!$relation->eagerLoad && !in_array($fieldName, $forceEagerLoad)) || in_array($fieldName, $eagerLoadDisable)) {
//                continue;
//            }
//
//            $relation->eagerLoad($models);
//            $didRelations[] = $fieldName;
//        }
//
//        $classGroups = [];
//
//        while (!empty($models)) {
//            $model = array_shift($models);
//
//            $classGroups[$model::class] ??= [];
//            $classGroups[$model::class][] = $model;
//        }
//
//        /**
//         * @var class-string<Model> $childModelClass
//         * @var Model[] $models
//         */
//        foreach ($classGroups as $childModelClass => $models) {
//            $relations = InternalModelData::getRelations($childModelClass);
//
//            foreach ($relations as $relation) {
//                $fieldName = $relation->fieldName;
//
//                if (in_array($fieldName, $didRelations) || (!$relation->eagerLoad && !in_array($fieldName, $forceEagerLoad)) || in_array($fieldName, $eagerLoadDisable)) {
//                    continue;
//                }
//
//                $relation->eagerLoad($models);
//            }
//        }
    }


}
