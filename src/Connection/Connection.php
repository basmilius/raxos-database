<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface, GrammarInterface, LoggerInterface};
use Raxos\Contract\Database\Orm\CacheInterface;
use Raxos\Contract\Database\Query\{QueryInterface, StatementInterface};
use Raxos\Database\Db;
use Raxos\Database\Error\{ExecutionException, InvalidTableException, NotConnectedException};
use Raxos\Database\Query\Error\NotInTransactionException;
use Raxos\Database\Query\Statement;
use SensitiveParameter;
use Throwable;

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
     * @param GrammarInterface $grammar
     * @param LoggerInterface $logger
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
        public readonly GrammarInterface $grammar,
        public readonly LoggerInterface $logger
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
    public function column(QueryInterface|string $query): string|int|false
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
        throw new ExecutionException($code, $message);
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
        throw new ExecutionException($code, $message);
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
     * @since 1.8.0
     */
    public function ping(): bool
    {
        try {
            $this->execute('DO 1');

            return true;
        } catch (Throwable) {
            return false;
        }
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
            throw new NotInTransactionException();
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
            throw new NotInTransactionException();
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
            throw new InvalidTableException($table);
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

        return isset($this->structure[$table]);
    }

    /**
     * Ensures that a connection is available.
     *
     * @return void
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    private function ensureConnected(): void
    {
        if (!$this->connected) {
            throw new NotConnectedException();
        }
    }

}
