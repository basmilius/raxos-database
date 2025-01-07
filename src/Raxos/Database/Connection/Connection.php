<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Database\Contract\{ConnectionInterface, QueryInterface, StatementInterface};
use Raxos\Database\Db;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\Grammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Query\Statement;
use SensitiveParameter;
use function array_key_exists;

/**
 * Class Connection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.4.0
 */
abstract class Connection implements ConnectionInterface
{

    public protected(set) ?PDO $pdo = null;
    public private(set) ?array $structure = null;

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public bool $connected {
        get => $this->pdo !== null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public bool $inTransaction {
        get => $this->pdo?->inTransaction() ?? false;
    }

    /**
     * Connection constructor.
     *
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param array|null $options
     * @param CacheInterface $cache
     * @param Grammar $grammar
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function __construct(
        #[SensitiveParameter] public readonly string $dsn,
        #[SensitiveParameter] public readonly ?string $username,
        #[SensitiveParameter] public readonly ?string $password,
        public readonly ?array $options,
        public readonly CacheInterface $cache,
        public readonly Grammar $grammar,
        public readonly Logger $logger
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function attribute(int $attribute): mixed
    {
        $this->ensureConnected();

        return $this->pdo->getAttribute($attribute);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function column(string|QueryInterface $query): string|int|false
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
     * @since 1.4.0
     */
    public function execute(string|QueryInterface $query): int
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
     * @since 1.4.0
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function lastInsertIdInteger(?string $name = null): int
    {
        return (int)$this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function prepare(string|QueryInterface $query, array $options = []): StatementInterface
    {
        return new Statement($this, $query, $options);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function quote(
        BackedEnum|float|bool|int|string $value,
        #[ExpectedValues(Db::TYPES)] int $type = PDO::PARAM_STR
    ): string
    {
        $this->ensureConnected();

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return $this->pdo->quote($value, $type);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function commit(): bool
    {
        if (!$this->inTransaction) {
            throw QueryException::notInTransaction();
        }

        return $this->pdo->commit();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function rollBack(): bool
    {
        if (!$this->inTransaction) {
            throw QueryException::notInTransaction();
        }

        return $this->pdo->rollBack();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function transaction(): bool
    {
        return $this->pdo?->beginTransaction() ?? false;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function tableColumnExists(string $table, string $column): bool
    {
        return $this->tableExists($table) && isset($this->structure[$table][$column]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function tableColumns(string $table): array
    {
        if (!$this->tableExists($table)) {
            throw SchemaException::invalidTable($table);
        }

        return $this->structure[$table] ?? [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function tableExists(string $table): bool
    {
        $this->structure ??= $this->loadDatabaseSchema();

        return array_key_exists($table, $this->structure);
    }

    /**
     * Ensures that a connection is available.
     *
     * @return void
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    private function ensureConnected(): void
    {
        if (!$this->connected) {
            throw ConnectionException::notConnected();
        }
    }

}
