<?php
declare(strict_types=1);

namespace Raxos\Database;

use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Database\Connection\ConnectionInterface;
use Raxos\Database\Connector\Connector;
use Raxos\Database\Error\{ConnectionException, DatabaseException};
use Raxos\Database\Query\{QueryInterface, Statement};
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

    /** @var ConnectionInterface[] */
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
     * @return ConnectionInterface
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function create(string $connectionClass, Connector $connector, string $id = 'default', bool $connectImmediately = true): ConnectionInterface
    {
        if (!is_subclass_of($connectionClass, ConnectionInterface::class)) {
            throw new ConnectionException(sprintf('Connection classes should implement "%s".', ConnectionInterface::class), ConnectionException::ERR_INVALID_CONNECTION);
        }

        /** @var ConnectionInterface $connection */
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
     * @return ConnectionInterface|null
     * @throws ConnectionException
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
     * @return ConnectionInterface
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getOrFail(?string $id = null): ConnectionInterface
    {
        $id ??= static::$connectionId;

        return static::get($id) ?? throw new ConnectionException(sprintf('Connection with ID "%s" not found or registered.', $id), ConnectionException::ERR_UNDEFINED_CONNECTION);
    }

    /**
     * Registers the given connection with the given ID.
     *
     * @param ConnectionInterface $connection
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function register(ConnectionInterface $connection): void
    {
        self::$connections[$connection->id] = $connection;
    }

    /**
     * Unregisters
     *
     * @param ConnectionInterface|string $idOrConnection
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function unregister(ConnectionInterface|string $idOrConnection): void
    {
        if ($idOrConnection instanceof ConnectionInterface) {
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::column()
     */
    public static function column(QueryInterface|string $query, ?string $id = null): string|int
    {
        return static::getOrFail($id)->column($query);
    }

    /**
     * Executes the given query and returns the amount of affected rows.
     *
     * @param QueryInterface|string $query
     * @param string|null $id
     *
     * @return int
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @return Statement
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::prepare()
     */
    public static function prepare(QueryInterface|string $query, array $options = [], ?string $id = null): Statement
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
     * @throws ConnectionException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::inTransaction()
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
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ConnectionInterface::transaction()
     */
    public static function transaction(?string $id = null): bool
    {
        return static::getOrFail($id)->transaction();
    }

}
