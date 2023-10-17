<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Db;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\{DatabaseException, ModelException, QueryException};
use Raxos\Database\Query\{Query, QueryInterface};
use Raxos\Database\Query\Struct\{ComparatorAwareLiteral, Literal, Value};
use Stringable;
use function array_map;
use function array_shift;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function Raxos\Database\Query\literal;

/**
 * Trait ModelDatabaseAccess
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
trait ModelDatabaseAccess
{

    /**
     * Queries all rows and returns them within the given bounds.
     *
     * @param int $offset
     * @param int $limit
     *
     * @return static[]
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function all(int $offset = 0, int $limit = 20): array
    {
        return static::select()
            ->limit($limit, $offset)
            ->array();
    }

    /**
     * Gets the cache instance.
     *
     * @return Cache
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::$cache
     */
    public static function cache(): Cache
    {
        return static::connection()->cache;
    }

    /**
     * Gets the dialect instance.
     *
     * @return Dialect
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::$dialect
     */
    public static function dialect(): Dialect
    {
        return static::connection()->dialect;
    }

    /**
     * Returns the fully qualified name for the given column as
     * a literal.
     *
     * @param string $column
     *
     * @return Literal
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.6
     */
    public static function col(string $column): Literal
    {
        return self::column($column, literal: true);
    }

    /**
     * Returns the fully qualified name for the given column.
     *
     * @param string $column
     * @param string|null $table
     * @param bool $literal
     *
     * @return Literal|string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function column(string $column, ?string $table = null, bool $literal = false): Literal|string
    {
        $table ??= static::table();
        $column = static::connection()
            ->dialect
            ->escapeFields("{$table}.{$column}");

        if ($literal) {
            return new Literal($column);
        }

        return $column;
    }

    /**
     * Gets the connection instance.
     *
     * @return Connection
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function connection(): Connection
    {
        static::initialize();

        return Db::getOrFail(static::$connectionId);
    }

    /**
     * Deletes a model row by its primary key(s).
     *
     * @param array|string|int $primaryKey
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function delete(array|string|int $primaryKey): void
    {
        static::cache()->removeByKey(static::class, $primaryKey);

        $query = static::query()
            ->deleteFrom(static::table());

        self::addPrimaryKeyClauses($query, $primaryKey);

        $query->run();
    }

    /**
     * Returns TRUE if there's a row for the given primary key(s) in the database.
     *
     * @param array|string|int $primaryKey
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function exists(array|string|int $primaryKey): bool
    {
        if (static::cache()->has(static::class, $primaryKey)) {
            return true;
        }

        $query = static::select()
            ->withoutModel();

        self::addPrimaryKeyClauses($query, $primaryKey);

        return $query->resultCount() >= 1;
    }

    /**
     * Finds all model rows with the given primary keys.
     *
     * @param array[]|string[]|int[] $primaryKeys
     *
     * @return static[]
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function find(array $primaryKeys): array
    {
        if (empty($primaryKeys)) {
            return [];
        }

        if (count($primaryKeys) === 1) {
            return [
                self::get($primaryKeys[0])
            ];
        }

        $query = static::select();

        self::addPrimaryKeyInClauses($query, $primaryKeys);

        return $query->array();
    }

    /**
     * Gets a model row by its primary key.
     *
     * @param array|string|int $primaryKey
     *
     * @return static|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function get(array|string|int $primaryKey): ?static
    {
        if (static::cache()->has(static::class, $primaryKey)) {
            return static::cache()->get(static::class, $primaryKey);
        }

        $query = static::select();

        self::addPrimaryKeyClauses($query, $primaryKey);

        return $query->single();
    }

    /**
     * Gets a model row by its primary key. Throws an exception when nothing
     * was found.
     *
     * @param array|string|int $primaryKey
     *
     * @return static
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getOrFail(array|string|int $primaryKey): static
    {
        return static::get($primaryKey) ?? throw new ModelException(sprintf('Model with primary key "%s" not found.', json_encode($primaryKey)), ModelException::ERR_NOT_FOUND);
    }

    /**
     * Sets up a having query for the model.
     *
     * @param BackedEnum|Stringable|Value|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|Value|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::having()
     */
    public static function having(BackedEnum|Stringable|Value|string|int|float|bool|null $lhs = null, BackedEnum|Stringable|Value|string|int|float|bool|null $cmp = null, BackedEnum|Stringable|Value|string|int|float|bool|null $rhs = null): QueryInterface
    {
        return static::select()
            ->having($lhs, $cmp, $rhs);
    }

    /**
     * Sets up a `having exists $query` query for the model.
     *
     * @param Query $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingExists()
     */
    public static function havingExists(Query $query): QueryInterface
    {
        return static::select()
            ->havingExists($query);
    }

    /**
     * Sets up a `having $field in $options` query for the model.
     *
     * @param Literal|string $field
     * @param array $options
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingIn()
     */
    public static function havingIn(Literal|string $field, array $options): QueryInterface
    {
        return static::select()
            ->havingIn($field, $options);
    }

    /**
     * Sets up a `having not exists $query` query for the model.
     *
     * @param Query $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingNotExists()
     */
    public static function havingNotExists(Query $query): QueryInterface
    {
        return static::select()
            ->havingNotExists($query);
    }

    /**
     * Sets up a `having $field not in $options` query for the model.
     *
     * @param Literal|string $field
     * @param array $options
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingIn()
     */
    public static function havingNotIn(Literal|string $field, array $options): QueryInterface
    {
        return static::select()
            ->havingNotIn($field, $options);
    }

    /**
     * Sets up a `having $field not null` query for the model.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingNotNull()
     */
    public static function havingNotNull(Literal|string $field): QueryInterface
    {
        return static::select()
            ->havingNotNull($field);
    }

    /**
     * Sets up a `having $field is null` query for the model.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingNull()
     */
    public static function havingNull(Literal|string $field): QueryInterface
    {
        return static::select()
            ->havingNull($field);
    }

    /**
     * Starts a new query for the current model.
     *
     * @param bool $isPrepared
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::query()
     * @see Query
     */
    public static function query(bool $isPrepared = true): QueryInterface
    {
        return static::connection()
            ->query($isPrepared)
            ->withModel(static::class);
    }

    /**
     * Starts a new simple select query for the current model.
     *
     * @param string[]|string|int $fields
     * @param bool $isPrepared
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::select()
     */
    public static function select(array|string|int $fields = [], bool $isPrepared = true): QueryInterface
    {
        return self::baseSelect(static::query($isPrepared)->select(...), $fields);
    }

    /**
     * Starts a new select found rows query for the current model.
     *
     * @param string[]|string|int $fields
     * @param bool $isPrepared
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::selectFoundRows()
     */
    public static function selectFoundRows(array|string|int $fields = [], bool $isPrepared = true): QueryInterface
    {
        return self::baseSelect(static::query($isPrepared)->selectFoundRows(...), $fields);
    }

    /**
     * Starts a new select distinct query for the current model.
     *
     * @param string[]|string|int $fields
     * @param bool $isPrepared
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::selectDistinct()
     */
    public static function selectDistinct(array|string|int $fields = [], bool $isPrepared = true): QueryInterface
    {
        return self::baseSelect(static::query($isPrepared)->selectDistinct(...), $fields);
    }

    /**
     * Starts a new select suffix query for the current model.
     *
     * @param string $suffix
     * @param string[]|string|int $fields
     * @param bool $isPrepared
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::selectSuffix()
     */
    public static function selectSuffix(string $suffix, array|string|int $fields = [], bool $isPrepared = true): QueryInterface
    {
        return self::baseSelect(fn(array|string|int $fields) => static::query($isPrepared)->selectSuffix($suffix, $fields), $fields);
    }

    /**
     * Updates the model row in the database.
     *
     * @param array|string|int $primaryKey
     * @param array $pairs
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::update()
     */
    public static function update(array|string|int $primaryKey, array $pairs): void
    {
        $query = static::query()
            ->update(static::table(), $pairs);

        self::addPrimaryKeyClauses($query, $primaryKey);

        $query->run();
    }

    /**
     * Sets up a where query for the model.
     *
     * @param BackedEnum|Stringable|Value|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|Value|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|Value|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::where()
     */
    public static function where(BackedEnum|Stringable|Value|string|int|float|bool|null $lhs = null, BackedEnum|Stringable|Value|string|int|float|bool|null $cmp = null, BackedEnum|Stringable|Value|string|int|float|bool|null $rhs = null): QueryInterface
    {
        return static::select()
            ->where($lhs, $cmp, $rhs);
    }

    /**
     * Sets up a `where exists $query` query for the model.
     *
     * @param Query $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereExists()
     */
    public static function whereExists(Query $query): QueryInterface
    {
        return static::select()
            ->whereExists($query);
    }

    /**
     * Sets up a `where $field in $options` query for the model.
     *
     * @param Literal|string $field
     * @param array $options
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereIn()
     */
    public static function whereIn(Literal|string $field, array $options): QueryInterface
    {
        return static::select()
            ->whereIn($field, $options);
    }

    /**
     * Sets up a `where not exists $query` query for the model.
     *
     * @param Query $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereNotExists()
     */
    public static function whereNotExists(Query $query): QueryInterface
    {
        return static::select()
            ->whereNotExists($query);
    }

    /**
     * Sets up a `where $field not in $options` query for the model.
     *
     * @param Literal|string $field
     * @param array $options
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereNotIn()
     */
    public static function whereNotIn(Literal|string $field, array $options): QueryInterface
    {
        return static::select()
            ->whereNotIn($field, $options);
    }

    /**
     * Sets up a `where $field not null` query for the model.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereNotNull()
     */
    public static function whereNotNull(Literal|string $field): QueryInterface
    {
        return static::select()
            ->whereNotNull($field);
    }

    /**
     * Sets up a `where $field is null` query for the model.
     *
     * @param Literal|string $field
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereNull()
     */
    public static function whereNull(Literal|string $field): QueryInterface
    {
        return static::select()
            ->whereNull($field);
    }

    /**
     * Adds primary key where clauses to the given query.
     *
     * @param QueryInterface $query
     * @param array|string|int $primaryKey
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function addPrimaryKeyClauses(QueryInterface $query, array|string|int $primaryKey): void
    {
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }

        foreach (static::getFields() as $field) {
            if (!$field->isPrimary) {
                continue;
            }

            if (empty($primaryKey)) {
                throw new QueryException('Too few primary key values.', QueryException::ERR_PRIMARY_KEY_MISMATCH);
            }

            $value = array_shift($primaryKey);
            $fieldName = $field->name;

            if (is_int($value) || is_float($value)) {
                $value = literal($value);
            }

            $query->where(static::col($fieldName), $value);
        }

        if (!empty($primaryKey)) {
            throw new QueryException('Too many primary key values.', QueryException::ERR_PRIMARY_KEY_MISMATCH);
        }
    }

    /**
     * Adds primary key where clauses to the given query for multiple results.
     *
     * @param QueryInterface $query
     * @param array[] $primaryKeys
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function addPrimaryKeyInClauses(QueryInterface $query, array $primaryKeys): void
    {
        $index = 0;

        if (!is_array($primaryKeys[0])) {
            $primaryKeys = [$primaryKeys];
        }

        $primaryKeyFields = static::getPrimaryKey();

        if (is_string($primaryKeyFields)) {
            $query->where(static::col($primaryKeyFields), ComparatorAwareLiteral::in(array_shift($primaryKeys)));
        } else {
            $primaryKeyFields = array_map(static::col(...), $primaryKeyFields);

            while (($keys = array_shift($primaryKeys)) !== null) {
                $query->parenthesis(function () use (&$index, $keys, $query, $primaryKeyFields): void {
                    foreach ($keys as $kIndex => $key) {
                        $primaryKeyField = $primaryKeyFields[$kIndex];

                        if ($kIndex === 0) {
                            $query->orWhere($primaryKeyField, $key);
                        } else {
                            $query->where($primaryKeyField, $key);
                        }
                    }
                });
            }
        }

        if (!empty($primaryKeys)) {
            throw new QueryException('Too many primary key values.', QueryException::ERR_PRIMARY_KEY_MISMATCH);
        }
    }

    /**
     * Starts a new select query for the current model.
     *
     * @param callable $fn
     * @param string[]|string|int $fields
     *
     * @return QueryInterface<static>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function baseSelect(callable $fn, array|string|int $fields): QueryInterface
    {
        return static::getDefaultJoins(
            $fn(static::getDefaultFields($fields))
                ->from(static::table())
        );
    }

}
