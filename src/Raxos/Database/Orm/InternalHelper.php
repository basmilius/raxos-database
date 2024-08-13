<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Orm\Definition\MacroDefinition;
use Raxos\Database\Orm\Relation\RelationInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use Raxos\Foundation\Collection\ArrayList;
use WeakMap;
use function array_shift;
use function is_array;
use function is_int;
use function is_string;

/**
 * Class InternalHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.16
 * @internal
 * @private
 */
final class InternalHelper
{

    /**
     * Composes a column literal based on the given column and table, if both of them
     * are null, null is returned.
     *
     * @param Dialect $dialect
     * @param string|null $column
     * @param string|null $table
     * @param ColumnLiteral|string $defaultColumn
     * @param string|null $defaultTable
     *
     * @return ColumnLiteral|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function composeRelationKey(Dialect $dialect, ?string $column, ?string $table, ColumnLiteral|string $defaultColumn, ?string $defaultTable = null): ?ColumnLiteral
    {
        if ($defaultColumn instanceof ColumnLiteral) {
            $column ??= $defaultColumn->column;
            $table ??= $defaultColumn->table;
        } else {
            $column ??= $defaultColumn;
            $table ??= $defaultTable;
        }

        return new ColumnLiteral($dialect, $column, $table);
    }

    /**
     * Gets the relation cache weak map for the given relation.
     *
     * @param RelationInterface $relation
     *
     * @return WeakMap<Model, ArrayList<Model>>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getRelationCache(RelationInterface $relation): WeakMap
    {
        InternalStructure::$relationCache ??= new WeakMap();

        return InternalStructure::$relationCache[$relation] ??= new WeakMap();
    }

    /**
     * Returns the cache helper for relations.
     *
     * @param Cache $cache
     *
     * @return callable(Model):Model
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    #[Pure]
    public static function getRelationCacheHelper(Cache $cache): callable
    {
        return static function (Model $reference) use ($cache): Model {
            $pk = $reference->getPrimaryKeyValues();

            if ($cache->has($reference::class, $pk)) {
                return $cache->get($reference::class, $pk);
            }

            return $reference;
        };
    }

    /**
     * Returns the first primary key as a column literal for relations.
     *
     * @param class-string<Model> $modelClass
     *
     * @return ColumnLiteral
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getRelationPrimaryKey(string $modelClass): ColumnLiteral
    {
        $key = $modelClass::getPrimaryKey();
        $table = $modelClass::table();

        if (is_array($key)) {
            $key = $key[0];
        }

        return new ColumnLiteral($modelClass::dialect(), $key, $table);
    }

    /**
     * Groups the given instances by their model. If a master model
     * is given, that model is filtered out, because it probably is
     * the master model within a polymorphic structure.
     *
     * @param Model[] $instances
     * @param string|null $polymorphicMasterClass
     *
     * @return array<class-string<Model>, Model[]>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function groupModels(array $instances, ?string $polymorphicMasterClass = null): array
    {
        $groups = [];

        while (!empty($instances)) {
            $instance = array_shift($instances);

            if ($instance::class === $polymorphicMasterClass) {
                continue;
            }

            $groups[$instance::class] ??= [];
            $groups[$instance::class][] = $instance;
        }

        return $groups;
    }

    /**
     * Returns TRUE if the given column of macro definition should
     * be visible.
     *
     * @param ColumnDefinition|MacroDefinition $definition
     * @param bool $forceVisible
     * @param bool $forceHidden
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see Model::toArray()
     */
    #[Pure]
    public static function isVisible(ColumnDefinition|MacroDefinition $definition, bool $forceVisible, bool $forceHidden): bool
    {
        if (InternalStructure::isRelation($definition)) {
            return $definition->isVisible && !$forceHidden || $forceVisible;
        }

        if ($definition instanceof ColumnDefinition) {
            return !$definition->isHidden && !$forceHidden || $forceVisible;
        }

        return $definition->isVisible && !$forceHidden || $forceVisible;
    }

    /**
     * Returns a normalized array for use in visibility.
     *
     * @param array|string $fields
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[Pure]
    public static function normalizeFieldsArray(array|string $fields): array
    {
        if (is_string($fields)) {
            return [$fields => null];
        }

        $result = [];

        foreach ($fields as $key => $value) {
            if (is_int($key)) {
                $result[$value] = null;
            } elseif ($value === null) {
                $result[$key] = null;
            } else {
                $result[$key] = self::normalizeFieldsArray($value);
            }
        }

        return $result;
    }

}
