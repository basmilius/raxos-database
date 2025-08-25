<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use BackedEnum;
use Generator;
use Raxos\Database\Contract\StructureInterface;
use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Orm\Attribute\{Alias, Caster, Column, Computed, ConnectionId, ForeignKey, Hidden, Immutable, Macro, OnDuplicateUpdate, Polymorphic, PrimaryKey, SoftDelete, Table, Visible};
use Raxos\Database\Orm\Caster\BooleanCaster;
use Raxos\Database\Orm\Contract\{AttributeInterface, CasterInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Definition\{ClassStructureDefinition, ColumnDefinition, MacroDefinition, PolymorphicDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Model;
use Raxos\Foundation\Util\ReflectionUtil;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use function array_values;
use function in_array;
use function is_a;
use function is_array;
use function is_callable;
use function is_subclass_of;

/**
 * Class StructureGenerator
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Structure
 * @since 1.0.17
 */
final class StructureGenerator
{

    private static array $structures = [];

    /**
     * Registers a structure.
     *
     * @param StructureInterface $structure
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function define(StructureInterface $structure): void
    {
        self::$structures[$structure->class] = $structure;
    }

    /**
     * Returns the structure for the given class.
     *
     * @template TModel of Model
     *
     * @param class-string<TModel> $class
     * @param StructureInterface|null $parent
     *
     * @return StructureInterface<TModel>
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function for(string $class, ?StructureInterface $parent = null): StructureInterface
    {
        if (isset(self::$structures[$class])) {
            return self::$structures[$class];
        }

        if (!is_subclass_of($class, Model::class)) {
            throw StructureException::notAModel($class);
        }

        try {
            $classRef = new ReflectionClass($class);
            $parentClassRef = $classRef->getParentClass();

            if ($parent === null && $parentClassRef->name !== Model::class) {
                $parent = self::for($parentClassRef->name);

                return self::for($class, $parent);
            }

            $model = self::class($classRef, $parent);
            $properties = [...self::properties($classRef)];

            if ($parent !== null) {
                $properties = [...$parent->properties, ...$properties];
            }

            $structure = self::$structures[$classRef->name] = new Structure(
                $classRef->name,
                $model->connectionId,
                $model->onDuplicateKeyUpdate,
                $model->polymorphic,
                $properties,
                $model->softDeleteColumn,
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
     * @param StructureInterface|null $parent
     *
     * @return ClassStructureDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function class(ReflectionClass $class, ?StructureInterface $parent = null): ClassStructureDefinition
    {
        $connectionId = $parent?->connection->id ?? 'default';
        $onDuplicateKeyUpdate = null;
        $polymorphic = null;
        $softDeleteColumn = $parent?->softDeleteColumn;
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

                case $attr instanceof SoftDelete:
                    $softDeleteColumn = $attr->column;
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
            $connectionId,
            $onDuplicateKeyUpdate,
            $polymorphic,
            $softDeleteColumn,
            $table
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
        static $isColumn = [];
        static $isMacro = [];
        static $isRelation = [];

        $attributes = $property->getAttributes(AttributeInterface::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $name = $attribute->getName();

            if ($isRelation[$name] ??= is_a($name, RelationAttributeInterface::class, true)) {
                return self::propertyRelation($property, $attributes);
            }

            if ($isMacro[$name] ??= is_a($name, Macro::class, true)) {
                return self::propertyMacro($property, $attributes);
            }

            if ($isColumn[$name] ??= is_a($name, Column::class, true)) {
                return self::propertyColumn($property, $attributes);
            }
        }

        return null;
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
        $enumClass = is_subclass_of($types[0], BackedEnum::class) ? $types[0] : null;
        $nullable = in_array('null', $types, true);
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
            $enumClass,
            $isForeignKey,
            $isPrimaryKey,
            $isComputed,
            $isImmutable,
            $key,
            $nullable,
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
