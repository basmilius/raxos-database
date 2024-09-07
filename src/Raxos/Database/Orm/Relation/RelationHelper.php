<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Grammar\Grammar;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Structure\Structure;
use Raxos\Database\Query\{InternalQueryInterface, QueryInterface};
use Raxos\Database\Query\Struct\ColumnLiteral;
use function is_numeric;

/**
 * Class RelationHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.1.0
 * @internal
 * @private
 */
final class RelationHelper
{

    /**
     * Composes a column literal based on the given column and table.
     *
     * @param Grammar $grammar
     * @param string|null $column
     * @param string|null $table
     * @param ColumnLiteral $default
     *
     * @return ColumnLiteral
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function composeKey(Grammar $grammar, ?string $column, ?string $table, ColumnLiteral $default): ColumnLiteral
    {
        $column ??= $default->column;
        $table ??= $default->table;

        return new ColumnLiteral($grammar, $column, $table);
    }

    /**
     * Returns the value of the declaring key.
     *
     * @param Model $instance
     * @param ColumnLiteral $declaringKey
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function declaringKeyValue(Model $instance, ColumnLiteral $declaringKey): mixed
    {
        $declaringValue = $instance->{$declaringKey->column};

        if ($declaringValue === null || (is_numeric($declaringValue) && (int)$declaringValue === 0)) {
            return null;
        }

        return $declaringValue;
    }

    /**
     * Finds a cached model and returns it.
     *
     * @param mixed $declaringValue
     * @param Structure $referenceStructure
     * @param ColumnLiteral $referenceKey
     *
     * @return Model|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function findCached(mixed $declaringValue, Structure $referenceStructure, ColumnLiteral $referenceKey): ?Model
    {
        return $referenceStructure->connection->cache
            ->find($referenceStructure->class, fn(Model $model) => $model->{$referenceKey->column} === $declaringValue);
    }

    /**
     * Ensures that the given function is called before relations
     * of a model are loaded.
     *
     * @param ModelArrayList<int, Model> $instances
     * @param callable(Model[], ModelArrayList<int, Model>):void $fn
     *
     * @return callable
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    #[Pure]
    public static function onBeforeRelations(ModelArrayList $instances, callable $fn): callable
    {
        return function (InternalQueryInterface&QueryInterface $query) use ($fn, $instances): QueryInterface {
            $query->_internal_beforeRelations(fn(array $results) => $fn($results, $instances));

            return $query;
        };
    }

}
