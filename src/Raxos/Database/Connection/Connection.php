<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use PDO;
use Raxos\Database\Connector\Connector;
use Raxos\Database\Db;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\Cache;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\QueryLog;
use Raxos\Database\Query\Statement;
use Raxos\Foundation\Event\Emitter;
use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * Class Connection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.0
 */
abstract class Connection
{

    use Emitter;

    public const EVENT_CONNECT = 'connect';
    public const EVENT_DISCONNECT = 'disconnect';

    public readonly Cache $cache;
    public readonly Dialect $dialect;

    protected ?array $columnsPerTable = null;
    protected ?QueryLog $logger = null;
    protected ?PDO $pdo = null;

    /**
     * Connection constructor.
     *
     * @param string $id
     * @param Connector $connector
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly string $id,
        public readonly Connector $connector
    )
    {
        $this->cache = new Cache();
        $this->dialect = $this->initializeDialect();
    }

    /**
     * Enables the built-in query logged.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function enableQueryLogging(): void
    {
        $this->logger = new QueryLog();
    }

    /**
     * Returns the amount rows that were found.
     *
     * @return int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function foundRows(): int;

    /**
     * Loads the database schema.
     *
     * @return array
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function loadDatabaseSchema(): array;

    /**
     * Starts a new query.
     *
     * @param bool $isPrepared
     *
     * @return Query
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function query(bool $isPrepared = true): Query;

    /**
     * Initializes the used dialect.
     *
     * @return Dialect
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    protected abstract function initializeDialect(): Dialect;

    /**
     * Connects to the database.
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function connect(): void
    {
        $this->pdo = $this->connector->createInstance();
        $this->emit(self::EVENT_CONNECT);
    }

    /**
     * Disconnects from the database.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->emit(self::EVENT_DISCONNECT);
    }

    /**
     * Gets a connection attribute.
     *
     * @param int $attribute
     *
     * @return mixed
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see PDO::getAttribute()
     */
    public function attribute(int $attribute): mixed
    {
        $this->ensureConnected();

        return $this->pdo->getAttribute($attribute);
    }

    /**
     * Executes the given query and returns the first column.
     *
     * @param Query|string $query
     *
     * @return string|int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function column(Query|string $query): string|int
    {
        if ($query instanceof Query) {
            $query = $query->toSql();
        }

        $smt = $this->pdo->query($query);
        $result = $smt->fetchColumn();
        $smt->closeCursor();

        return $result;
    }

    /**
     * Executes the given query and returns the amount of affected rows.
     *
     * @param Query|string $query
     *
     * @return int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see PDO::exec()
     */
    public function execute(Query|string $query): int
    {
        if ($query instanceof Query) {
            $query = $query->toSql();
        }

        $result = $this->pdo->exec($query);

        if ($result !== false) {
            return $result;
        }

        throw $this->throwFromError();
    }

    /**
     * Returns TRUE if we're connected.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Gets the last insert id as string.
     *
     * @param string|null $name
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see PDO::lastInsertId()
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Gets the last insert id as int.
     *
     * @param string|null $name
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see PDO::lastInsertId()
     */
    public function lastInsertIdInteger(?string $name = null): int
    {
        return (int)$this->pdo->lastInsertId($name);
    }

    /**
     * Initializes a prepared statement.
     *
     * @param Query|string $query
     * @param array $options
     *
     * @return Statement
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function prepare(Query|string $query, array $options = []): Statement
    {
        return new Statement($this, $query, $options);
    }

    /**
     * Quotes the given value.
     *
     * @param string|int|float|bool $value
     * @param int $type
     *
     * @return string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function quote(string|int|float|bool $value, #[ExpectedValues(Db::TYPES)] int $type = PDO::PARAM_STR): string
    {
        $this->ensureConnected();

        return $this->pdo->quote((string)$value, $type);
    }

    /**
     * Returns TRUE if the given column exists in the given table.
     *
     * @param string $table
     * @param string $column
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function tableColumnExists(string $table, string $column): bool
    {
        return $this->tableExists($table) && in_array($column, $this->columnsPerTable[$table]);
    }

    /**
     * Gets all the columns of the given table.
     *
     * @param string $table
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function tableColumns(string $table): bool
    {
        if (!$this->tableExists($table)) {
            throw new ConnectionException(sprintf('Table "%s" does not exists in the current database.', $table), ConnectionException::ERR_SCHEMA_ERROR);
        }

        return $this->columnsPerTable[$table];
    }

    /**
     * Returns TRUE if the given table exists.
     *
     * @param string $table
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function tableExists(string $table): bool
    {
        $this->columnsPerTable ??= $this->loadDatabaseSchema();

        return array_key_exists($table, $this->columnsPerTable);
    }

    /**
     * Commits the transaction.
     *
     * @return bool
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function commit(): bool
    {
        if (!$this->pdo->inTransaction()) {
            throw new QueryException('There is no running transaction.', QueryException::ERR_NO_TRANSACTION);
        }

        return $this->pdo->commit();
    }

    /**
     * Returns TRUE when in a transaction.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Rolls the transaction back.
     *
     * @return bool
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rollBack(): bool
    {
        if (!$this->pdo->inTransaction()) {
            throw new QueryException('There is no running transaction.', QueryException::ERR_NO_TRANSACTION);
        }

        return $this->pdo->rollBack();
    }

    /**
     * Begins a transaction.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Gets the query logger.
     *
     * @return QueryLog|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLogger(): ?QueryLog
    {
        return $this->logger;
    }

    /**
     * Gets the PDO instance.
     *
     * @return PDO|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Ensures that there is an active connection.
     *
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function ensureConnected(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        throw new ConnectionException('Not connected to a database.', ConnectionException::ERR_DISCONNECTED);
    }

    /**
     * Throws from the last pdo error.
     *
     * @return DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function throwFromError(): DatabaseException
    {
        [, $code, $message] = $this->pdo->errorInfo();

        return DatabaseException::throw($code, $message);
    }

}
