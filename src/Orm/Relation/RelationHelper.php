<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Collection\ArrayList;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\GrammarInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Query\{InternalQueryInterface, QueryInterface};
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Literal\ColumnLiteral;
use function is_numeric;

/**
 * Class RelationHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.1.0
 */
final class RelationHelper
{

    /**
     * Composes a column literal based on the given column and table.
     *
     * @param GrammarInterface $grammar
     * @param string|null $column
     * @param string|null $table
     * @param ColumnLiteral $default
     *
     * @return ColumnLiteral
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function composeKey(GrammarInterface $grammar, ?string $column, ?string $table, ColumnLiteral $default): ColumnLiteral
    {
        static $cache = [];

        $column ??= $default->column;
        $table ??= $default->table;

        return $cache["{$table}:{$column}"] ??= new ColumnLiteral($grammar, $column, $table);
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
     * @param StructureInterface $referenceStructure
     * @param ColumnLiteral $referenceKey
     *
     * @return Model|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function findCached(mixed $declaringValue, StructureInterface $referenceStructure, ColumnLiteral $referenceKey): ?Model
    {
        return $referenceStructure->connection->cache
            ->find($referenceStructure->class, static fn(Model $model) => $model->{$referenceKey->column} === $declaringValue);
    }

    /**
     * Ensures that the given function is called before relations
     * of a model are loaded.
     *
     * @param ArrayListInterface<int, Model> $instances
     * @param callable(ArrayListInterface<int, Model>, ArrayListInterface<int, Model>):void $fn
     *
     * @return callable
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function onBeforeRelations(ArrayListInterface $instances, callable $fn): callable
    {
        return static function (InternalQueryInterface&QueryInterface $query) use ($fn, $instances): QueryInterface {
            $query->_internal_beforeRelations(static fn(ArrayListInterface $results) => $fn($results, $instances));

            return $query;
        };
    }

    /**
     * Partitions the foreign keys into already loaded instances and to
     * be loaded instances.
     *
     * @param StructureInterface $referenceStructure
     * @param ArrayListInterface<int, string|int|null> $foreignKeys
     *
     * @return array{
     *     0: ArrayListInterface<int, Model>,
     *     1: ArrayListInterface<int, string|int|null>
     * }
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function partitionModels(StructureInterface $referenceStructure, ArrayListInterface $foreignKeys): array
    {
        $cache = $referenceStructure->connection->cache;
        $cached = new ArrayList();
        $uncached = new ArrayList();

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey === null) {
                continue;
            }

            if ($cache->has($referenceStructure->class, $foreignKey)) {
                $cached->append($cache->get($referenceStructure->class, $foreignKey));
                continue;
            }

            $uncached->append($foreignKey);
        }

        return [
            $cached,
            $uncached
        ];
    }

}
