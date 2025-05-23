<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use Raxos\Database\Contract\{QueryInterface, QueryLiteralInterface, QueryValueInterface};
use Raxos\Database\Contract\ConnectionInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\Error\{InstanceException, RelationException, StructureException};
use Raxos\Database\Orm\Structure\{Structure, StructureGenerator};
use Raxos\Database\Query\Literal\ColumnLiteral;
use Raxos\Database\Query\Select;
use Raxos\Foundation\Contract\ArrayableInterface;
use Raxos\Foundation\Contract\ArrayListInterface;
use Stringable;
use function is_array;

/**
 * Trait Queryable
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
trait Queryable
{

    /**
     * Returns the fully qualified name for the given column.
     *
     * @param string $key
     *
     * @return ColumnLiteral
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see Structure::getColumn()
     */
    public static function col(string $key): ColumnLiteral
    {
        static $cache = [];

        $structure = StructureGenerator::for(static::class);

        if ($key === '*') {
            return $cache["{$structure->table}:*"] ??= new ColumnLiteral($structure->connection->grammar, $key, $structure->table);
        }

        return $structure->getColumn($key);
    }

    /**
     * Starts a new query for the model.
     *
     * @param bool $prepared
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see ConnectionInterface::query()
     */
    public static function query(bool $prepared = true): QueryInterface
    {
        return StructureGenerator::for(static::class)->connection
            ->query($prepared)
            ->withModel(static::class);
    }

    /**
     * Returns the table name for the model.
     *
     * @return string
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see Structure::$table
     */
    public static function table(): string
    {
        return StructureGenerator::for(static::class)->table;
    }

    /**
     * Queries all records and returns them within the given bounds.
     *
     * @param int $offset
     * @param int $limit
     *
     * @return ArrayListInterface<int, static>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function all(int $offset = 0, int $limit = 20): ArrayListInterface
    {
        return self::select()
            ->limit($limit, $offset)
            ->arrayList();
    }

    /**
     * Deletes a morel record by its primary key(s)
     *
     * @param array|string|int $primaryKey
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::deleteFrom()
     */
    public static function delete(array|string|int $primaryKey): void
    {
        $cache = StructureGenerator::for(static::class)->connection->cache;
        $cache->unset(static::class, $primaryKey);

        self::query()
            ->deleteFrom(self::table())
            ->wherePrimaryKey(static::class, $primaryKey)
            ->run();
    }

    /**
     * Returns TRUE if a record with the given primary key exists.
     *
     * @param array|string|int $primaryKey
     *
     * @return bool
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function exists(array|string|int $primaryKey): bool
    {
        $cache = StructureGenerator::for(static::class)->connection->cache;

        if ($cache->has(static::class, $primaryKey)) {
            return true;
        }

        return self::select()
                ->withoutModel()
                ->wherePrimaryKey(static::class, $primaryKey)
                ->resultCount() >= 1;
    }

    /**
     * Finds multiple records by their primary key.
     *
     * @param array $primaryKeys
     *
     * @return ModelArrayList<int, static>
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::wherePrimaryKeyIn()
     */
    public static function find(array $primaryKeys): ModelArrayList
    {
        if (empty($primaryKeys)) {
            return new ModelArrayList();
        }

        $cache = StructureGenerator::for(static::class)->connection->cache;
        $results = new ModelArrayList();
        $missing = [];

        foreach ($primaryKeys as $primaryKey) {
            if ($cache->has(static::class, $primaryKey)) {
                $results->append($cache->get(static::class, $primaryKey));
                continue;
            }

            $missing[] = $primaryKey;
        }

        if (empty($missing)) {
            return $results;
        }

        return $results->merge(
            self::select()
                ->wherePrimaryKeyIn(static::class, $missing)
                ->arrayList()
        );
    }

    /**
     * Returns a record by its primary key.
     *
     * @param array|string|int $primaryKey
     *
     * @return static|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::wherePrimaryKey()
     */
    public static function single(array|string|int $primaryKey): ?static
    {
        $cache = StructureGenerator::for(static::class)->connection->cache;

        if ($cache->has(static::class, $primaryKey)) {
            return $cache->get(static::class, $primaryKey);
        }

        return self::select()
            ->wherePrimaryKey(static::class, $primaryKey)
            ->single();
    }

    /**
     * Returns a record by its primary key. Throws an exception when the
     * record could not be found.
     *
     * @param array|string|int $primaryKey
     *
     * @return static
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws InstanceException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see self::single()
     */
    public static function singleOrFail(array|string|int $primaryKey): static
    {
        return self::single($primaryKey) ?? throw InstanceException::notFound(static::class, $primaryKey);
    }

    /**
     * Updates a record by its primary key with the given values.
     *
     * @param array|string|int $primaryKey
     * @param array<string, mixed> $values
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::update()
     */
    public static function update(array|string|int $primaryKey, array $values): void
    {
        self::query()
            ->update(self::table(), $values)
            ->wherePrimaryKey(static::class, $primaryKey)
            ->run();
    }

    /**
     * Returns a `having $lhs $cmp $rhs` query for the model.
     *
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::having()
     */
    public static function having(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): QueryInterface
    {
        return self::select()
            ->having($lhs, $cmp, $rhs);
    }

    /**
     * Returns a `having exists ($query)` query for the model.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::havingExists()
     */
    public static function havingExists(QueryInterface $query): QueryInterface
    {
        return self::select()
            ->havingExists($query);
    }

    /**
     * Returns a `having $column in ($options)` query for the model.
     *
     * @param ColumnLiteral $column
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $options
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::havingIn()
     */
    public static function havingIn(ColumnLiteral $column, ArrayableInterface|array $options): QueryInterface
    {
        return self::select()
            ->havingIn($column, $options);
    }

    /**
     * Returns a `having not exists ($query)` query for the model.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::havingNotExists()
     */
    public static function havingNotExists(QueryInterface $query): QueryInterface
    {
        return self::select()
            ->havingNotExists($query);
    }

    /**
     * Returns a `having $column not in ($options)` query for the model.
     *
     * @param ColumnLiteral $column
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $options
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::havingNotIn()
     */
    public static function havingNotIn(ColumnLiteral $column, ArrayableInterface|array $options): QueryInterface
    {
        return self::select()
            ->havingNotIn($column, $options);
    }

    /**
     * Returns a `having $column is not null` query for the model.
     *
     * @param ColumnLiteral $column
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::havingNotNull()
     */
    public static function havingNotNull(ColumnLiteral $column): QueryInterface
    {
        return self::select()
            ->havingNotNull($column);
    }

    /**
     * Returns a `having $column is null` query for the model.
     *
     * @param ColumnLiteral $column
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::havingNull()
     */
    public static function havingNull(ColumnLiteral $column): QueryInterface
    {
        return self::select()
            ->havingNull($column);
    }

    /**
     * Returns a new select query for the model.
     *
     * @param Select|QueryValueInterface|Stringable|array|string|int $keys
     * @param bool $prepared
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::select()
     */
    public static function select(Select|QueryValueInterface|Stringable|array|string|int $keys = [], bool $prepared = true): QueryInterface
    {
        return self::baseSelect(self::query($prepared)->select(...), $keys);
    }

    /**
     * Returns a new select distinct query for the model.
     *
     * @param Select|QueryValueInterface|Stringable|array|string|int $keys
     * @param bool $prepared
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::selectDistinct()
     */
    public static function selectDistinct(Select|QueryValueInterface|Stringable|array|string|int $keys = [], bool $prepared = true): QueryInterface
    {
        return self::baseSelect(self::query($prepared)->selectDistinct(...), $keys);
    }

    /**
     * Returns a new select found rows query for the model.
     *
     * @param Select|QueryValueInterface|Stringable|array|string|int $keys
     * @param bool $prepared
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::selectFoundRows()
     */
    public static function selectFoundRows(Select|QueryValueInterface|Stringable|array|string|int $keys = [], bool $prepared = true): QueryInterface
    {
        return self::baseSelect(self::query($prepared)->selectFoundRows(...), $keys);
    }

    /**
     * Returns a new select suffix query for the model.
     *
     * @param string $suffix
     * @param Select|QueryValueInterface|Stringable|array|string|int $keys
     * @param bool $prepared
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::selectSuffix()
     */
    public static function selectSuffix(string $suffix, Select|QueryValueInterface|Stringable|array|string|int $keys = [], bool $prepared = true): QueryInterface
    {
        return self::baseSelect(static fn(array|string|int $keys) => self::query($prepared)->selectSuffix($suffix, $keys), $keys);
    }

    /**
     * Returns a `where $lhs $cmp $rhs` query for the model.
     *
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::where()
     */
    public static function where(
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|QueryValueInterface|string|int|float|bool|null $rhs = null
    ): QueryInterface
    {
        return self::select()
            ->where($lhs, $cmp, $rhs);
    }

    /**
     * Returns a `where exists ($query)` query for the model.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::whereExists()
     */
    public static function whereExists(QueryInterface $query): QueryInterface
    {
        return self::select()
            ->whereExists($query);
    }

    /**
     * Returns a `where $column in ($options)` query for the model.
     *
     * @param ColumnLiteral $column
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $options
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::whereIn()
     */
    public static function whereIn(ColumnLiteral $column, ArrayableInterface|array $options): QueryInterface
    {
        return self::select()
            ->whereIn($column, $options);
    }

    /**
     * Returns a `where not exists ($query)` query for the model.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::whereNotExists()
     */
    public static function whereNotExists(QueryInterface $query): QueryInterface
    {
        return self::select()
            ->whereNotExists($query);
    }

    /**
     * Returns a `where $column not in ($options)` query for the model.
     *
     * @param ColumnLiteral $column
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $options
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::whereNotIn()
     */
    public static function whereNotIn(ColumnLiteral $column, ArrayableInterface|array $options): QueryInterface
    {
        return self::select()
            ->whereNotIn($column, $options);
    }

    /**
     * Returns a `where $column is not null` query for the model.
     *
     * @param ColumnLiteral $column
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::whereNotNull()
     */
    public static function whereNotNull(ColumnLiteral $column): QueryInterface
    {
        return self::select()
            ->whereNotNull($column);
    }

    /**
     * Returns a `where $column is null` query for the model.
     *
     * @param ColumnLiteral $column
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     * @see QueryInterface::whereNull()
     */
    public static function whereNull(ColumnLiteral $column): QueryInterface
    {
        return self::select()
            ->whereNull($column);
    }

    /**
     * Returns a new select query for the model.
     *
     * @param callable(Select):QueryInterface<static> $compose
     * @param Select|QueryValueInterface|Stringable|array|string|int $keys
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private static function baseSelect(callable $compose, Select|QueryValueInterface|Stringable|array|string|int $keys): QueryInterface
    {
        if (!($keys instanceof Select)) {
            if (!is_array($keys)) {
                $keys = [$keys];
            }

            $keys = Select::of($keys);
        }

        return static::getQueryableJoins(
            $compose(static::getQueryableColumns($keys))
                ->from(self::table())
        );
    }

}
