<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Db;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Value;
use function array_shift;
use function is_array;
use function json_encode;

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
     * @see Connection::getCache()
     */
    public static function cache(): Cache
    {
        return static::connection()->getCache();
    }

    /**
     * Gets the dialect instance.
     *
     * @return Dialect
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::getDialect()
     */
    public static function dialect(): Dialect
    {
        return static::connection()->getDialect();
    }

    /**
     * Returns the fully qualified name for the given column.
     *
     * @param string $column
     * @param string|null $table
     *
     * @return string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function column(string $column, ?string $table = null): string
    {
        $table ??= static::getTable();

        return static::connection()
            ->getDialect()
            ->escapeFields("{$table}.{$column}");
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
            ->deleteFrom(static::getTable());

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

        $query = static::select(1)
            ->withoutModel();

        self::addPrimaryKeyClauses($query, $primaryKey);

        return $query->single() !== null;
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

        // todo(Bas): Find models that are already in cache. We don't need
        //  to query those again as we're using existing models.

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
     * @return static|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getOrFail(array|string|int $primaryKey): ?static
    {
        return static::get($primaryKey) ?? throw new ModelException(sprintf('Model with primary key "%s" not found.', json_encode($primaryKey)), ModelException::ERR_NOT_FOUND);
    }

    /**
     * Sets up a having query for the model.
     *
     * @param Value|string|int|float|bool|null $field
     * @param Value|string|int|float|bool|null $comparator
     * @param Value|string|int|float|bool|null $value
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::having()
     */
    public static function having(Value|string|int|float|bool|null $field = null, Value|string|int|float|bool|null $comparator = null, Value|string|int|float|bool|null $value = null): Query
    {
        return static::select()
            ->having($field, $comparator, $value);
    }

    /**
     * Starts a new query for the current model.
     *
     * @param bool $isPrepared
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::query()
     * @see Query
     */
    public static function query(bool $isPrepared = true): Query
    {
        return static::connection()
            ->query($isPrepared)
            ->withModel(static::class);
    }

    /**
     * Starts a new simple select query for the current model.
     *
     * @param string[]|string|int $fields
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::select()
     */
    public static function select(array|string|int $fields = []): Query
    {
        return self::baseSelect(fn(array|string|int $fields) => static::query()->select($fields), $fields);
    }

    /**
     * Starts a new select found rows query for the current model.
     *
     * @param string[]|string|int $fields
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::selectFoundRows()
     */
    public static function selectFoundRows(array|string|int $fields = []): Query
    {
        return self::baseSelect(fn(array|string|int $fields) => static::query()->selectFoundRows($fields), $fields);
    }

    /**
     * Starts a new select distinct query for the current model.
     *
     * @param string[]|string|int $fields
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::selectDistinct()
     */
    public static function selectDistinct(array|string|int $fields = []): Query
    {
        return self::baseSelect(fn(array|string|int $fields) => static::query()->selectDistinct($fields), $fields);
    }

    /**
     * Starts a new select suffix query for the current model.
     *
     * @param string $suffix
     * @param string[]|string|int $fields
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::selectSuffix()
     */
    public static function selectSuffix(string $suffix, array|string|int $fields = []): Query
    {
        return self::baseSelect(fn(array|string|int $fields) => static::query()->selectSuffix($suffix, $fields), $fields);
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
     * @see Query::update()
     */
    public static function update(array|string|int $primaryKey, array $pairs): void
    {
        $query = static::query()
            ->update(static::getTable(), $pairs);

        self::addPrimaryKeyClauses($query, $primaryKey);

        $query->run();
    }

    /**
     * Sets up a where query for the model.
     *
     * @param Value|string|int|float|bool|null $field
     * @param Value|string|int|float|bool|null $comparator
     * @param Value|string|int|float|bool|null $value
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::where()
     */
    public static function where(Value|string|int|float|bool|null $field = null, Value|string|int|float|bool|null $comparator = null, Value|string|int|float|bool|null $value = null): Query
    {
        return static::select()
            ->where($field, $comparator, $value);
    }

    /**
     * Adds primary key where clauses to the given query.
     *
     * @param Query $query
     * @param array|string|int $primaryKey
     * @param bool $startWithWhere
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function addPrimaryKeyClauses(Query $query, array|string|int $primaryKey, bool $startWithWhere = true): void
    {
        $index = $startWithWhere ? 0 : 1;

        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }

        foreach (static::getFields() as $field => ['is_primary' => $isPrimary]) {
            if (!$isPrimary) {
                continue;
            }

            if (empty($primaryKey)) {
                throw new QueryException('Too few primary key values.', QueryException::ERR_PRIMARY_KEY_MISMATCH);
            }

            $value = array_shift($primaryKey);

            $field = static::getFieldName($field);

            if ($index++ === 0) {
                $query->where(static::column($field), $value);
            } else {
                $query->and(static::column($field), $value);
            }
        }

        if (!empty($primaryKey)) {
            throw new QueryException('Too many primary key values.', QueryException::ERR_PRIMARY_KEY_MISMATCH);
        }
    }

    /**
     * Adds primary key where clauses to the given query for multiple results.
     *
     * @param Query $query
     * @param array[] $primaryKeys
     * @param bool $startWithWhere
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function addPrimaryKeyInClauses(Query $query, array $primaryKeys, bool $startWithWhere = true): void
    {
        $index = $startWithWhere ? 0 : 1;

        if (!is_array($primaryKeys[0])) {
            $primaryKeys = [$primaryKeys];
        }

        foreach (static::getFields() as $field => ['is_primary' => $isPrimary]) {
            if (!$isPrimary) {
                continue;
            }

            if (empty($primaryKeys)) {
                throw new QueryException('Too few primary key values.', QueryException::ERR_PRIMARY_KEY_MISMATCH);
            }

            $values = array_shift($primaryKeys);

            if ($index++ === 0) {
                $query->where(static::column($field), ComparatorAwareLiteral::in($values));
            } else {
                $query->and(static::column($field), ComparatorAwareLiteral::in($values));
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
     * @return Query
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function baseSelect(callable $fn, array|string|int $fields): Query
    {
        return $fn($fields)
            ->from(static::getTable());
    }

}
