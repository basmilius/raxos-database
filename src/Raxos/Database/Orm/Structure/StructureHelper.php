<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Query\Struct\ColumnLiteral;
use function is_int;
use function is_string;

/**
 * Class StructureHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Structure
 * @since 1.0.17
 */
final class StructureHelper
{

    /**
     * Composes a column literal based on the given column and table, for
     * use within relations.
     *
     * @param Dialect $dialect
     * @param string|null $column
     * @param string|null $table
     * @param ColumnLiteral $default
     *
     * @return ColumnLiteral
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function composeRelationKey(Dialect $dialect, ?string $column, ?string $table, ColumnLiteral $default): ColumnLiteral
    {
        $column ??= $default->column;
        $table ??= $default->table;

        return new ColumnLiteral($dialect, $column, $table);
    }

    /**
     * Groups the given instances by their model. If a master model
     * is given, that model is filtered out, because it probably is
     * the master model within a polymorphic structure.
     *
     * @param ModelArrayList $instances
     * @param string|null $polymorphicMasterClass
     *
     * @return ModelArrayList<class-string<Model>, ModelArrayList>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function groupInstances(ModelArrayList $instances, ?string $polymorphicMasterClass = null): ModelArrayList
    {
        return $instances
            ->filter(fn(Model $instance) => $instance::class !== $polymorphicMasterClass)
            ->groupBy(fn(Model $instance) => $instance::class);
    }

    /**
     * Returns TRUE if the given property should be visible by default.
     *
     * @param PropertyDefinition $property
     * @param bool $forceVisible
     * @param bool $forceHidden
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function isVisible(PropertyDefinition $property, bool $forceVisible, bool $forceHidden): bool
    {
        return match (true) {
            $property instanceof ColumnDefinition => !$property->isHidden && !$forceHidden || $forceVisible,
            $property instanceof MacroDefinition, $property instanceof RelationDefinition => $property->isVisible && !$forceHidden || $forceVisible
        };
    }

    /**
     * Returns a normalized array for use in visibility.
     *
     * @param string[]|string $keys
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[Pure]
    public static function normalizeKeys(array|string $keys): array
    {
        if (is_string($keys)) {
            return [$keys => null];
        }

        $result = [];

        foreach ($keys as $key => $value) {
            if (is_int($key)) {
                $result[$value] = null;
            } elseif ($value === null) {
                $result[$key] = null;
            } else {
                $result[$key] = self::normalizeKeys($value);
            }
        }

        return $result;
    }

}
