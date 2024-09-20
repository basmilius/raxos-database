<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Database\Connector\Connector;
use Raxos\Database\Contract\ConnectionInterface;
use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Contract\StatementInterface;
use Raxos\Database\Db;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\Grammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Query\Statement;
use function array_key_exists;
use function in_array;

/**
 * Class Connection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.0
 */
abstract class Connection implements ConnectionInterface
{

    protected ?array $columnsPerTable = null;
    protected ?PDO $pdo = null;

    /**
     * Connection constructor.
     *
     * @param string $id
     * @param CacheInterface $cache
     * @param Connector $connector
     * @param Grammar $grammar
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly string $id,
        public readonly Connector $connector,
        public readonly CacheInterface $cache,
        public readonly Grammar $grammar,
        public readonly Logger $logger
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function connect(): void
    {
        $this->pdo = $this->connector->createInstance();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function attribute(int $attribute): mixed
    {
        $this->ensureConnected();

        return $this->pdo->getAttribute($attribute);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function column(QueryInterface|string $query): string|int
    {
        if ($query instanceof QueryInterface) {
            $query = $query->toSql();
        }

        $smt = $this->pdo->query($query);
        $result = $smt->fetchColumn();
        $smt->closeCursor();

        if ($result !== false) {
            return $result;
        }

        [, $code, $message] = $this->pdo->errorInfo();
        throw ExecutionException::of($code, $message);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function execute(QueryInterface|string $query): int
    {
        if ($query instanceof QueryInterface) {
            $query = $query->toSql();
        }

        $result = $this->pdo->exec($query);

        if ($result !== false) {
            return $result;
        }

        [, $code, $message] = $this->pdo->errorInfo();
        throw ExecutionException::of($code, $message);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function lastInsertIdInteger(?string $name = null): int
    {
        return (int)$this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function prepare(QueryInterface|string $query, array $options = []): StatementInterface
    {
        return new Statement($this, $query, $options);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function quote(BackedEnum|string|int|float|bool $value, #[ExpectedValues(Db::TYPES)] int $type = PDO::PARAM_STR): string
    {
        $this->ensureConnected();

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return $this->pdo->quote((string)$value, $type);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function tableColumnExists(string $table, string $column): bool
    {
        return $this->tableExists($table) && in_array($column, $this->columnsPerTable[$table], true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function tableColumns(string $table): array
    {
        if (!$this->tableExists($table)) {
            throw SchemaException::invalidTable($table);
        }

        return $this->columnsPerTable[$table] ?? [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function tableExists(string $table): bool
    {
        $this->columnsPerTable ??= $this->loadDatabaseSchema();

        return array_key_exists($table, $this->columnsPerTable);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function commit(): bool
    {
        if (!$this->pdo->inTransaction()) {
            throw QueryException::notInTransaction();
        }

        return $this->pdo->commit();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rollBack(): bool
    {
        if (!$this->pdo->inTransaction()) {
            throw QueryException::notInTransaction();
        }

        return $this->pdo->rollBack();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * {@inheritdoc}
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getPdo(): PDO
    {
        $this->ensureConnected();

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

        throw ConnectionException::notConnected();
    }

}
