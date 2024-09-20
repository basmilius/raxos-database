<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use Generator;
use Raxos\Database\Db;
use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Orm\Attribute\{Alias, Caster, Column, Computed, ConnectionId, ForeignKey, Hidden, Immutable, Macro, OnDuplicateUpdate, Polymorphic, PrimaryKey, Table, Visible};
use Raxos\Database\Orm\Caster\BooleanCaster;
use Raxos\Database\Orm\Contract\{AttributeInterface, CasterInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Definition\{ClassStructureDefinition, ColumnDefinition, MacroDefinition, PolymorphicDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Model;
use Raxos\Foundation\Util\{ArrayUtil, ReflectionUtil};
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use function array_values;
use function is_a;
use function is_array;
use function is_callable;
use function is_subclass_of;
use function iterator_to_array;

/**
 * Class StructureGenerator
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Structure
 * @since 1.0.17
 * @internal
 * @private
 */
final class StructureGenerator
{

    private static array $structures = [];

    /**
     * Returns the structure for the given class.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $class
     *
     * @return Structure<TModel>
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function for(string $class, ?Structure $parent = null): Structure
    {
        if (isset(self::$structures[$class])) {
            return self::$structures[$class];
        }

        if (!is_subclass_of($class, Model::class)) {
            throw StructureException::notAModel($class);
        }

        try {
            $classRef = new ReflectionClass($class);
            $model = self::class($classRef, $parent);
            $connection = Db::getOrFail($model->connectionId);
            $properties = iterator_to_array(self::properties($classRef));

            if ($parent !== null) {
                $properties = [...$parent->properties, ...$properties];
            }

            $structure = self::$structures[$classRef->name] = new Structure(
                $classRef->name,
                $connection,
                $model->onDuplicateKeyUpdate,
                $model->polymorphic,
                $properties,
                $model->table,
                $parent
            );

            if ($model->polymorphic === null) {
                return $structure;
            }

            $classes = array_values($model->polymorphic->map);

            foreach ($classes as $subClass) {
                self::for($subClass, $structure);
            }

            return $structure;
        } catch (ConnectionException $err) {
            throw StructureException::connectionFailed($class, $err);
        } catch (ReflectionException $err) {
            throw StructureException::reflectionError($class, $err);
        }
    }

    /**
     * Returns the structure for the given class.
     *
     * @param ReflectionClass $class
     * @param Structure|null $parent
     *
     * @return ClassStructureDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function class(ReflectionClass $class, ?Structure $parent = null): ClassStructureDefinition
    {
        $connectionId = $parent?->connection->id ?? 'default';
        $onDuplicateKeyUpdate = null;
        $polymorphic = null;
        $table = $parent?->table;

        $attributes = $class->getAttributes(AttributeInterface::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof ConnectionId:
                    $connectionId = $attr->connectionId;
                    break;

                case $attr instanceof OnDuplicateUpdate:
                    $onDuplicateKeyUpdate = $attr->fields;

                    if (!is_array($onDuplicateKeyUpdate)) {
                        $onDuplicateKeyUpdate = [$onDuplicateKeyUpdate];
                    }

                    if (empty($onDuplicateKeyUpdate)) {
                        $onDuplicateKeyUpdate = null;
                    }
                    break;

                case $attr instanceof Polymorphic:
                    $polymorphic = new PolymorphicDefinition($attr->column, $attr->map);
                    break;

                case $attr instanceof Table:
                    $table = $attr->name;
                    break;
            }
        }

        if ($table === null) {
            throw StructureException::missingTable($class->name);
        }

        return new ClassStructureDefinition(
            connectionId: $connectionId,
            onDuplicateKeyUpdate: $onDuplicateKeyUpdate,
            polymorphic: $polymorphic,
            table: $table
        );
    }

    /**
     * Generates the definitions for the properties of the model.
     *
     * @param ReflectionClass $class
     *
     * @return Generator<PropertyDefinition>
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function properties(ReflectionClass $class): Generator
    {
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            if ($property->class !== $class->name) {
                continue;
            }

            $definition = self::property($property);

            if ($definition === null) {
                continue;
            }

            yield $definition;
        }
    }

    /**
     * Returns the definition for the given property.
     *
     * @param ReflectionProperty $property
     *
     * @return PropertyDefinition|null
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function property(ReflectionProperty $property): ?PropertyDefinition
    {
        $attributes = $property->getAttributes(AttributeInterface::class, ReflectionAttribute::IS_INSTANCEOF);
        $isRelation = ArrayUtil::some($attributes, fn(ReflectionAttribute $attribute) => is_a($attribute->getName(), RelationAttributeInterface::class, true));
        $isMacro = ArrayUtil::some($attributes, fn(ReflectionAttribute $attribute) => is_a($attribute->getName(), Macro::class, true));
        $isColumn = ArrayUtil::some($attributes, fn(ReflectionAttribute $attribute) => is_a($attribute->getName(), Column::class, true));

        return match (true) {
            $isRelation => self::propertyRelation($property, $attributes),
            $isMacro => self::propertyMacro($property, $attributes),
            $isColumn => self::propertyColumn($property, $attributes),
            default => null
        };
    }

    /**
     * Returns the column definition for the given property.
     *
     * @param ReflectionProperty $property
     * @param ReflectionAttribute[] $attributes
     *
     * @return ColumnDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function propertyColumn(ReflectionProperty $property, array $attributes): ColumnDefinition
    {
        $alias = null;
        $caster = null;
        $defaultValue = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
        $hasAlias = false;
        $isForeignKey = false;
        $isPrimaryKey = false;
        $isComputed = false;
        $isImmutable = false;
        $isHidden = false;
        $isVisible = false;
        $key = $property->name;
        $types = ($type = $property->getType()) !== null ? ReflectionUtil::getTypes($type) ?? [] : [];
        $visibleOnly = null;

        foreach ($attributes as $attribute) {
            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    $hasAlias = true;
                    break;

                case $attr instanceof Caster:
                    if (!is_subclass_of($attr->casterClass, CasterInterface::class)) {
                        throw StructureException::invalidCaster($property->class, $property->name);
                    }

                    $caster = $attr->casterClass;
                    break;

                case $attr instanceof Column:
                    $isForeignKey = $attr instanceof ForeignKey;
                    $isPrimaryKey = $attr instanceof PrimaryKey;
                    $isImmutable = $isPrimaryKey;
                    $key = $attr->key ?? $property->name;
                    break;

                case $attr instanceof Computed:
                    $isComputed = true;
                    break;

                case $attr instanceof Immutable:
                    $isImmutable = true;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;

                    if ($attr->only !== null) {
                        $visibleOnly = StructureHelper::normalizeKeys($attr->only);
                    }
                    break;
            }
        }

        if ($alias === null && $hasAlias) {
            $alias = $key;
        }

        if ($caster === null && $types[0] === 'bool') {
            $caster = BooleanCaster::class;
        }

        return new ColumnDefinition(
            $caster,
            $defaultValue,
            $isForeignKey,
            $isPrimaryKey,
            $isComputed,
            $isImmutable,
            $key,
            $types,
            $visibleOnly,
            $property->name,
            $alias,
            $isHidden,
            $isVisible
        );
    }

    /**
     * Returns the macro definition for the given property.
     *
     * @param ReflectionProperty $property
     * @param ReflectionAttribute[] $attributes
     *
     * @return MacroDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function propertyMacro(ReflectionProperty $property, array $attributes): MacroDefinition
    {
        $alias = null;
        $callback = null;
        $isCached = false;
        $isHidden = false;
        $isVisible = false;

        foreach ($attributes as $attribute) {
            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    break;

                case $attr instanceof Macro:
                    $callback = $attr->callback;
                    $isCached = $attr->isCached;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;
                    break;
            }
        }

        if (!is_callable($callback)) {
            throw StructureException::invalidMacro($property->class, $property->name);
        }

        return new MacroDefinition(
            $callback,
            $isCached,
            $property->name,
            $alias,
            $isHidden,
            $isVisible
        );
    }

    /**
     * Returns the relation definition for the given property.
     *
     * @param ReflectionProperty $property
     * @param ReflectionAttribute[] $attributes
     *
     * @return RelationDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function propertyRelation(ReflectionProperty $property, array $attributes): RelationDefinition
    {
        $alias = null;
        $isHidden = false;
        $isVisible = false;
        $relation = null;
        $types = ($type = $property->getType()) !== null ? ReflectionUtil::getTypes($type) ?? [] : [];
        $visibleOnly = null;

        foreach ($attributes as $attribute) {
            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    break;

                case $attr instanceof RelationAttributeInterface:
                    $relation = $attr;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;

                    if ($attr->only !== null) {
                        $visibleOnly = StructureHelper::normalizeKeys($attr->only);
                    }
                    break;
            }
        }

        if ($relation === null) {
            throw StructureException::invalidRelation($property->class, $property->name);
        }

        return new RelationDefinition(
            $relation,
            $types,
            $visibleOnly,
            $property->name,
            $alias,
            $isHidden,
            $isVisible
        );
    }

}
