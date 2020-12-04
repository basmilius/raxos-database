<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Database\Connector\Connector;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\Statement;
use Raxos\Foundation\Event\Emitter;

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

    public const PARAM_TYPES = [
        PDO::PARAM_BOOL,
        PDO::PARAM_NULL,
        PDO::PARAM_INT,
        PDO::PARAM_STR
    ];

    protected Dialect $dialect;
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
        protected string $id,
        protected Connector $connector
    )
    {
        $this->dialect = $this->initializeDialect();
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
     * Gets the connection id.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getId(): string
    {
        return $this->id;
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
     * Executes the given query and returns the first column.
     *
     * @param Query|string $query
     *
     * @return string|int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function queryColumn(Query|string $query): string|int
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
    public function quote(string|int|float|bool $value, #[ExpectedValues(self::PARAM_TYPES)] int $type = PDO::PARAM_STR): string
    {
        $this->ensureConnected();

        return $this->pdo->quote((string)$value, $type);
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
     * Gets the used connector.
     *
     * @return Connector
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getConnector(): Connector
    {
        return $this->connector;
    }

    /**
     * Gets the used dialect.
     *
     * @return Dialect
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getDialect(): Dialect
    {
        return $this->dialect;
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
