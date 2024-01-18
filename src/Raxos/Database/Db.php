<?php
declare(strict_types=1);

namespace Raxos\Database;

use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Connector\Connector;
use Raxos\Database\Error\{ConnectionException, DatabaseException};
use Raxos\Database\Query\{Query, QueryInterface, Statement};
use function is_subclass_of;
use function sprintf;

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

    /** @var Connection[] */
    private static array $connections = [];
    private static array $connected = [];

    /**
     * Creates and registers a new connection instance.
     *
     * @param string $connectionClass
     * @param Connector $connector
     * @param string $id
     * @param bool $connectImmediately
     *
     * @return Connection
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function create(string $connectionClass, Connector $connector, string $id = 'default', bool $connectImmediately = true): Connection
    {
        if (!is_subclass_of($connectionClass, Connection::class)) {
            throw new ConnectionException(sprintf('Connection classes should extend "%s".', Connection::class), ConnectionException::ERR_INVALID_CONNECTION);
        }

        /** @var Connection $connection */
        $connection = new $connectionClass($id, $connector);

        if ($connectImmediately) {
            self::$connected[$id] = true;
            $connection->connect();
        } else {
            self::$connected[$id] = false;
        }

        static::register($connection);

        return $connection;
    }

    /**
     * Gets a connection by the given ID, or the default one when the ID
     * was omitted.
     *
     * @param string|null $id
     *
     * @return Connection|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function get(?string $id = null): ?Connection
    {
        $id ??= static::$connectionId;
        $connection = self::$connections[$id] ?? null;

        if ($connection === null) {
            return null;
        }

        if (!self::$connected[$id] && !$connection->isConnected()) {
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
     * @return Connection
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getOrFail(?string $id = null): Connection
    {
        $id ??= static::$connectionId;

        return static::get($id) ?? throw new ConnectionException(sprintf('Connection with ID "%s" not found or registered.', $id), ConnectionException::ERR_UNDEFINED_CONNECTION);
    }

    /**
     * Registers the given connection with the given ID.
     *
     * @param Connection $connection
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function register(Connection $connection): void
    {
        self::$connections[$connection->id] = $connection;
    }

    /**
     * Unregisters
     *
     * @param Connection|string $idOrConnection
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function unregister(Connection|string $idOrConnection): void
    {
        if ($idOrConnection instanceof Connection) {
            $idOrConnection = $idOrConnection->id;
        }

        unset(self::$connections[$idOrConnection]);
    }

    /**
     * Gets a connection attribute.
     *
     * @param int $attribute
     * @param string|null $id
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::attribute()
     */
    public static function attribute(#[ExpectedValues(self::ATTRIBUTES)] int $attribute, ?string $id = null): mixed
    {
        return static::getOrFail($id)->attribute($attribute);
    }

    /**
     * Executes the given query and returns the first column.
     *
     * @param Query|string $query
     * @param string|null $id
     *
     * @return string|int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::column()
     */
    public static function column(Query|string $query, ?string $id = null): string|int
    {
        return static::getOrFail($id)->column($query);
    }

    /**
     * Executes the given query and returns the amount of affected rows.
     *
     * @param Query|string $query
     * @param string|null $id
     *
     * @return int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::execute()
     */
    public static function execute(Query|string $query, ?string $id = null): int
    {
        return static::getOrFail($id)->execute($query);
    }

    /**
     * Returns the amount rows that were found.
     *
     * @param string|null $id
     *
     * @return int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::foundRows()
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::lastInsertId()
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::lastInsertIdInteger()
     */
    public static function lastInsertIdInteger(?string $name = null, ?string $id = null): int
    {
        return static::getOrFail($id)->lastInsertIdInteger($name);
    }

    /**
     * Initializes a prepared statement.
     *
     * @param Query|string $query
     * @param array $options
     * @param string|null $id
     *
     * @return Statement
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::prepare()
     */
    public static function prepare(Query|string $query, array $options = [], ?string $id = null): Statement
    {
        return static::getOrFail($id)->prepare($query, $options);
    }

    /**
     * Starts a new query.
     *
     * @param bool $isPrepared
     * @param string|null $id
     *
     * @return QueryInterface
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::query()
     */
    public static function query(bool $isPrepared = true, ?string $id = null): QueryInterface
    {
        return static::getOrFail($id)->query($isPrepared);
    }

    /**
     * Quotes the given value.
     *
     * @param string|int|float|bool $value
     * @param int $type
     * @param string|null $id
     *
     * @return string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::quote()
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::tableColumnExists()
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
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::tableColumns()
     */
    public static function tableColumns(string $table, ?string $id = null): bool
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Connection::tableExists()
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function inTransaction(?string $id = null): bool
    {
        return static::getOrFail($id)->inTransaction();
    }

    /**
     * Rolls the transaction back.
     *
     * @param string|null $id
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function transaction(?string $id = null): bool
    {
        return static::getOrFail($id)->transaction();
    }

}
