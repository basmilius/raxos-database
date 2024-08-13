<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use Raxos\Database\Connection\ConnectionInterface;
use Raxos\Database\Db;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\{DatabaseException, ModelException, QueryException};
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\{ColumnLiteral, ComparatorAwareLiteral, Literal, ValueInterface};
use Stringable;
use function array_fill_keys;
use function array_is_list;
use function array_map;
use function array_merge;
use function array_shift;
use function count;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

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
     * Gets the value(s) of the primary key(s) of the model.
     *
     * @return array|string|int|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getPrimaryKeyValues(): array|string|int|null
    {
        $keys = static::getPrimaryKey();

        if ($keys === null) {
            return null;
        }

        if (is_string($keys)) {
            $keys = [$keys];
        }

        $values = array_map($this->getValue(...), $keys);

        if (count($values) === 1) {
            return $values[0];
        }

        return $values;
    }

    /**
     * Queries the given relation.
     *
     * @param string $field
     *
     * @return QueryInterface
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @no-named-arguments
     */
    public function queryRelation(string $field): QueryInterface
    {
        $def = InternalStructure::getField(static::class, $field);

        if ($def === null || !InternalStructure::isRelation($def)) {
            throw new ModelException(sprintf('Field %s is not a relation.', $field), ModelException::ERR_RELATION_NOT_FOUND);
        }

        return InternalStructure::getRelation(static::class, $def)
            ->query($this);
    }

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
     * @see ConnectionInterface::$cache
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
     * @see ConnectionInterface::$dialect
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
     * @return ColumnLiteral
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.6
     */
    public static function col(string $column): ColumnLiteral
    {
        return new ColumnLiteral(self::dialect(), $column, static::table());
    }

    /**
     * Returns the fully qualified name for the given column.
     *
     * @param string $column
     *
     * @return string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function column(string $column): string
    {
        return (string)self::col($column);
    }

    /**
     * Gets the connection instance.
     *
     * @return ConnectionInterface
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function connection(): ConnectionInterface
    {
        InternalStructure::initialize(static::class);

        return Db::getOrFail(InternalStructure::$connectionId[static::class] ?? 'default');
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

        static::query()
            ->deleteFrom(static::table())
            ->wherePrimaryKey(static::class, $primaryKey)
            ->run();
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

        return static::select()
                ->withoutModel()
                ->wherePrimaryKey(static::class, $primaryKey)
                ->resultCount() >= 1;
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

        return static::select()
            ->wherePrimaryKey(static::class, $primaryKey)
            ->single();
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
        return static::get($primaryKey) ?? throw new ModelException(sprintf('Model with primary key "%s" not found.', implode(', ', $primaryKey)), ModelException::ERR_NOT_FOUND);
    }

    /**
     * Sets up a having query for the model.
     *
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::having()
     */
    public static function having(
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): QueryInterface
    {
        return static::select()
            ->having($lhs, $cmp, $rhs);
    }

    /**
     * Sets up a `having exists $query` query for the model.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingExists()
     */
    public static function havingExists(QueryInterface $query): QueryInterface
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
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::havingNotExists()
     */
    public static function havingNotExists(QueryInterface $query): QueryInterface
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
     * @see ConnectionInterface::query()
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
        return self::baseSelect(static fn(array|string|int $fields) => static::query($isPrepared)->selectSuffix($suffix, $fields), $fields);
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
        static::query()
            ->update(static::table(), $pairs)
            ->wherePrimaryKey(static::class, $primaryKey)
            ->run();
    }

    /**
     * Sets up a where query for the model.
     *
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp
     * @param BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::where()
     */
    public static function where(
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): QueryInterface
    {
        return static::select()
            ->where($lhs, $cmp, $rhs);
    }

    /**
     * Sets up a `where exists $query` query for the model.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereExists()
     */
    public static function whereExists(QueryInterface $query): QueryInterface
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
     * @param QueryInterface $query
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see QueryInterface::whereNotExists()
     */
    public static function whereNotExists(QueryInterface $query): QueryInterface
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
     * Gets the primary key(s) of the model.
     *
     * @return string[]|string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function getPrimaryKey(): array|string|null
    {
        static $knownPrimaryKey = [];

        return $knownPrimaryKey[static::class] ??= (static function (): array|string|null {
            $columns = [];

            foreach (InternalStructure::getColumns(static::class) as $def) {
                if ($def->isPrimary) {
                    $columns[] = $def->key;
                }
            }

            $length = count($columns);

            if ($length === 0) {
                return null;
            }

            if ($length === 1) {
                return $columns[0];
            }

            return $columns;
        })();
    }

    /**
     * Gets the table of the model.
     *
     * @return string
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function table(): string
    {
        if (isset(InternalStructure::$table[static::class])) {
            return InternalStructure::$table[static::class];
        }

        InternalStructure::initialize(static::class);

        return InternalStructure::$table[static::class] ?? throw new ModelException(sprintf('Model "%s" does not have a table assigned.', static::class), ModelException::ERR_NO_TABLE_ASSIGNED);
    }


    /**
     * Ensures that the given fields are returned as array.
     *
     * @param array|string|int $fields
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    protected static function ensureArrayFields(array|string|int $fields): array
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if (array_is_list($fields)) {
            $fields = array_fill_keys($fields, true);
        }

        return $fields;
    }

    /**
     * Extends the given fields with the given extended fields.
     *
     * @param array|string|int $fields
     * @param array $extendedFields
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    protected static function extendFields(array|string|int $fields, array $extendedFields): array
    {
        return array_merge(static::ensureArrayFields($fields), $extendedFields);
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
        if (!is_array($primaryKeys[0])) {
            $primaryKeys = [$primaryKeys];
        }

        $primaryKeyFields = static::getPrimaryKey();

        if (is_string($primaryKeyFields)) {
            $query->where(static::col($primaryKeyFields), ComparatorAwareLiteral::in(array_shift($primaryKeys)));
        } else {
            $primaryKeyFields = array_map(static::col(...), $primaryKeyFields);

            foreach ($primaryKeys as $keys) {
                $query->parenthesis(function () use ($keys, $query, $primaryKeyFields): void {
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
     * @param callable(array|int|string):QueryInterface $fn
     * @param string[]|string|int $fields
     *
     * @return QueryInterface<static>
     * @throws DatabaseException
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
