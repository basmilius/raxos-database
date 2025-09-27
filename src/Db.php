<?php
declare(strict_types=1);

namespace Raxos\Database;

use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface};
use Raxos\Contract\Database\Query\{QueryInterface, StatementInterface};
use Raxos\Database\Error\InvalidConnectionException;

/**
 * Class Db
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database
 * @since 1.0.0
 */
class Db
{

    public const array ATTRIBUTES = [
        PDO::ATTR_AUTOCOMMIT,
        PDO::ATTR_CASE,
        PDO::ATTR_CLIENT_VERSION,
        PDO::ATTR_CONNECTION_STATUS,
        PDO::ATTR_DRIVER_NAME,
        PDO::ATTR_ERRMODE,
        PDO::ATTR_ORACLE_NULLS,
        PDO::ATTR_PERSISTENT,
        PDO::ATTR_PREFETCH,
        PDO::ATTR_SERVER_INFO,
        PDO::ATTR_SERVER_VERSION,
        PDO::ATTR_TIMEOUT
    ];

    public const array TYPES = [
        PDO::PARAM_BOOL,
        PDO::PARAM_NULL,
        PDO::PARAM_INT,
        PDO::PARAM_STR
    ];

    protected static string $connectionId = 'default';

    /** @var ConnectionInterface[] */
    private static array $connections = [];
    private static array $connected = [];

    /**
     * Gets a connection by the given ID, or the default one when the ID
     * was omitted.
     *
     * @param string|null $id
     *
     * @return ConnectionInterface|null
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function get(?string $id = null): ?ConnectionInterface
    {
        $id ??= static::$connectionId;
        $connection = self::$connections[$id] ?? null;

        if ($connection === null) {
            return null;
        }

        if (!self::$connected[$id] && !$connection->connected) {
            self::$connected[$id] = true;
            $connection->connect();
        }

        return $connection;
    }

    /**
     * Gets a connection by the given ID, or the default one when the ID
     * was omitted. Throws an exception when no connection was found.
     *
     * @param string|null $id
     *
     * @return ConnectionInterface
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getOrFail(?string $id = null): ConnectionInterface
    {
        $id ??= static::$connectionId;

        return static::get($id) ?? throw new InvalidConnectionException($id);
    }

    /**
     * Registers the given connection with the given ID.
     *
     * @param ConnectionInterface $connection
     * @param string|null $id
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function register(ConnectionInterface $connection, ?string $id = null): void
    {
        self::$connections[$id ??= self::$connectionId] = $connection;
        self::$connected[$id] = false;
    }

    /**
     * Unregisters the given connection or connection with the given ID.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function unregister(string $id): void
    {
        unset(self::$connections[$id]);
    }

    /**
     * Gets a connection attribute.
     *
     * @param int $attribute
     * @param string|null $id
     *
     * @return mixed
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::attribute()
     */
    public static function attribute(#[ExpectedValues(self::ATTRIBUTES)] int $attribute, ?string $id = null): mixed
    {
        return static::getOrFail($id)->attribute($attribute);
    }

    /**
     * Executes the given query and returns the first column.
     *
     * @param QueryInterface|string $query
     * @param string|null $id
     *
     * @return string|int
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::column()
     */
    public static function column(QueryInterface|string $query, ?string $id = null): string|int
    {
        return static::getOrFail($id)->column($query);
    }

    /**
     * Executes the given query and returns the number of affected rows.
     *
     * @param QueryInterface|string $query
     * @param string|null $id
     *
     * @return int
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::execute()
     */
    public static function execute(QueryInterface|string $query, ?string $id = null): int
    {
        return static::getOrFail($id)->execute($query);
    }

    /**
     * Returns the amount rows that were found.
     *
     * @param string|null $id
     *
     * @return int
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::foundRows()
     */
    public static function foundRows(?string $id = null): int
    {
        return static::getOrFail($id)->foundRows();
    }

    /**
     * Gets the last insert id as string.
     *
     * @param string|null $name
     * @param string|null $id
     *
     * @return string
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::lastInsertId()
     */
    public static function lastInsertId(?string $name = null, ?string $id = null): string
    {
        return static::getOrFail($id)->lastInsertId($name);
    }

    /**
     * Gets the last insert id as int.
     *
     * @param string|null $name
     * @param string|null $id
     *
     * @return int
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::lastInsertIdInteger()
     */
    public static function lastInsertIdInteger(?string $name = null, ?string $id = null): int
    {
        return static::getOrFail($id)->lastInsertIdInteger($name);
    }

    /**
     * Initializes a prepared statement.
     *
     * @param QueryInterface|string $query
     * @param array $options
     * @param string|null $id
     *
     * @return StatementInterface
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::prepare()
     */
    public static function prepare(QueryInterface|string $query, array $options = [], ?string $id = null): StatementInterface
    {
        return static::getOrFail($id)->prepare($query, $options);
    }

    /**
     * Starts a new query.
     *
     * @param bool $prepared
     * @param string|null $id
     *
     * @return QueryInterface
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::query()
     */
    public static function query(bool $prepared = true, ?string $id = null): QueryInterface
    {
        return static::getOrFail($id)
            ->query($prepared);
    }

    /**
     * Quotes the given value.
     *
     * @param string|int|float|bool $value
     * @param int $type
     * @param string|null $id
     *
     * @return string
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::quote()
     */
    public static function quote(string|int|float|bool $value, #[ExpectedValues(self::TYPES)] int $type = PDO::PARAM_STR, ?string $id = null): string
    {
        return static::getOrFail($id)->quote($value, $type);
    }

    /**
     * Returns TRUE if the given column exists in the given table.
     *
     * @param string $table
     * @param string $column
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::tableColumnExists()
     */
    public static function tableColumnExists(string $table, string $column, ?string $id = null): bool
    {
        return static::getOrFail($id)->tableColumnExists($table, $column);
    }

    /**
     * Gets all the columns of the given table.
     *
     * @param string $table
     * @param string|null $id
     *
     * @return string[]
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::tableColumns()
     */
    public static function tableColumns(string $table, ?string $id = null): array
    {
        return static::getOrFail($id)->tableColumns($table);
    }

    /**
     * Returns TRUE if the given table exists.
     *
     * @param string $table
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::tableExists()
     */
    public static function tableExists(string $table, ?string $id = null): bool
    {
        return static::getOrFail($id)->tableExists($table);
    }

    /**
     * Commits the transaction.
     *
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::commit()
     */
    public static function commit(?string $id = null): bool
    {
        return static::getOrFail($id)->commit();
    }

    /**
     * Returns TRUE when in a transaction.
     *
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::inTransaction()
     */
    public static function inTransaction(?string $id = null): bool
    {
        return static::getOrFail($id)->inTransaction;
    }

    /**
     * Rolls the transaction back.
     *
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::rollBack()
     */
    public static function rollBack(?string $id = null): bool
    {
        return static::getOrFail($id)->rollBack();
    }

    /**
     * Begins a transaction.
     *
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::transaction()
     */
    public static function transaction(?string $id = null): bool
    {
        return static::getOrFail($id)->transaction();
    }

}
