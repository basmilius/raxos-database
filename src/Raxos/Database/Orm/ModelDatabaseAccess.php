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
use Stringable;
use function array_map;
use function array_shift;
use function count;
use function is_array;
use function is_string;
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
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return Query<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::having()
     */
    public static function having(Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): Query
    {
        return static::select()
            ->having($field, $comparator, $value);
    }

    /**
     * Starts a new query for the current model.
     *
     * @param bool $isPrepared
     *
     * @return Query<static>
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
     * @return Query<static>
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
     * @return Query<static>
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
     * @return Query<static>
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
     * @return Query<static>
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
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return Query<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Query::where()
     */
    public static function where(Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): Query
    {
        return static::select()
            ->where($field, $comparator, $value);
    }

    /**
     * Adds primary key where clauses to the given query.
     *
     * @param Query $query
     * @param array|string|int $primaryKey
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function addPrimaryKeyClauses(Query $query, array|string|int $primaryKey): void
    {
        $index = 0;

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

            if ($index++ === 0) {
                $query->where(static::column($fieldName), $value);
            } else {
                $query->and(static::column($fieldName), $value);
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
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function addPrimaryKeyInClauses(Query $query, array $primaryKeys): void
    {
        $index = 0;

        if (!is_array($primaryKeys[0])) {
            $primaryKeys = [$primaryKeys];
        }

        $primaryKeyFields = static::getPrimaryKey();

        if (is_string($primaryKeyFields)) {
            $query->where(static::column($primaryKeyFields), ComparatorAwareLiteral::in(array_shift($primaryKeys)));
        } else {
            $primaryKeyFields = array_map([static::class, 'column'], $primaryKeyFields);

            while (($keys = array_shift($primaryKeys)) !== null) {
                $query->parenthesis(function () use (&$index, $keys, $query, $primaryKeyFields): void {
                    foreach ($keys as $kIndex => $key) {
                        $primaryKeyField = $primaryKeyFields[$kIndex];

                        if ($index++ === 0) {
                            $query->where($primaryKeyField, $key);
                        } else if ($kIndex === 0) {
                            $query->or($primaryKeyField, $key);
                        } else {
                            $query->and($primaryKeyField, $key);
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
     * @return Query<static>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function baseSelect(callable $fn, array|string|int $fields): Query
    {
        return $fn(static::getDefaultFields($fields))
            ->from(static::getTable());
    }

}
