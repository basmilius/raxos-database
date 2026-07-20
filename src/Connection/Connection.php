<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use PDOException;
use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface, GrammarInterface, LoggerInterface};
use Raxos\Contract\Database\Orm\CacheInterface;
use Raxos\Contract\Database\Query\{QueryInterface, StatementInterface};
use Raxos\Database\Db;
use Raxos\Database\Error\{ExecutionException, InvalidTableException, NotConnectedException};
use Raxos\Database\Query\Error\{NotInTransactionException, RollbackOnlyTransactionException};
use Raxos\Database\Query\Statement;
use SensitiveParameter;
use Throwable;
use function array_key_exists;
use function in_array;
use function is_array;
use function str_contains;
use function strtolower;

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
    public bool $autoReconnect = false;

    private int $transactionDepth = 0;
    private bool $transactionRollbackOnly = false;

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
        $this->ensureConnected();

        if ($query instanceof QueryInterface) {
            return $this->runWithRecovery(static fn(): string|int|false => $query->statement()->fetchColumn());
        }

        return $this->runWithRecovery(function () use ($query): string|int|false {
            try {
                $smt = $this->pdo->query($query);
            } catch (PDOException $err) {
                throw ExecutionException::fromException($err);
            }

            if ($smt === false) {
                throw ExecutionException::fromErrorInfo($this->pdo);
            }

            try {
                $result = $smt->fetchColumn();

                if ($result === false) {
                    $errorInfo = $this->pdo->errorInfo();

                    if ($errorInfo[0] !== '00000' && $errorInfo[0] !== null) {
                        throw ExecutionException::fromErrorInfo($this->pdo);
                    }

                    return false;
                }

                return $result;
            } finally {
                $smt->closeCursor();
            }
        });
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function execute(string|QueryInterface $query): int
    {
        $this->ensureConnected();

        if ($query instanceof QueryInterface) {
            return $this->runWithRecovery(static fn(): int => $query->statement()->run());
        }

        return $this->runWithRecovery(function () use ($query): int {
            try {
                $result = $this->pdo->exec($query);
            } catch (PDOException $err) {
                throw ExecutionException::fromException($err);
            }

            if ($result !== false) {
                return $result;
            }

            throw ExecutionException::fromErrorInfo($this->pdo);
        });
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function lastInsertId(?string $name = null): string
    {
        $this->ensureConnected();

        return $this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function lastInsertIdInteger(?string $name = null): int
    {
        $this->ensureConnected();

        return (int)$this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.8.0
     */
    public function ping(): bool
    {
        // note(Bas): generic fallback. Subclasses override with a dialect-specific
        //  query (`DO 1` on MySQL/MariaDB, `SELECT 1` already works everywhere
        //  but is the only universally portable choice).
        try {
            $this->execute('select 1');

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

        return $this->pdo->quote((string)$value, $type);
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
        $this->ensureConnected();

        if ($this->transactionDepth === 0 || !$this->inTransaction) {
            throw new NotInTransactionException();
        }

        if ($this->transactionDepth === 1) {
            if ($this->transactionRollbackOnly) {
                $depth = $this->transactionDepth;
                $this->transactionDepth = 0;
                $this->transactionRollbackOnly = false;
                $this->pdo->rollBack();

                throw new RollbackOnlyTransactionException(transactionDepth: $depth);
            }

            $this->transactionDepth = 0;

            return $this->pdo->commit();
        }

        $name = $this->savepointName($this->transactionDepth);
        $this->pdo->exec("release savepoint {$name}");
        --$this->transactionDepth;

        return true;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function rollBack(): bool
    {
        $this->ensureConnected();

        if ($this->transactionDepth === 0 || !$this->inTransaction) {
            throw new NotInTransactionException();
        }

        if ($this->transactionDepth === 1) {
            $this->transactionDepth = 0;
            $this->transactionRollbackOnly = false;

            return $this->pdo->rollBack();
        }

        $name = $this->savepointName($this->transactionDepth);
        $this->pdo->exec("rollback to savepoint {$name}");
        --$this->transactionDepth;

        // note(Bas): a nested rollback leaves outer levels in an unsafe state
        //  if they later try to commit, because the work between savepoint
        //  start and rollback may have committed side effects in the outer
        //  scope. Mark the outer transaction as rollback-only.
        $this->transactionRollbackOnly = true;

        return true;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function transaction(): bool
    {
        $this->ensureConnected();

        if ($this->transactionDepth === 0) {
            $started = $this->pdo->beginTransaction();
            $this->transactionDepth = 1;
            $this->transactionRollbackOnly = false;

            return $started;
        }

        ++$this->transactionDepth;
        $name = $this->savepointName($this->transactionDepth);
        $this->pdo->exec("savepoint {$name}");

        return true;
    }

    /**
     * @param int $depth
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    private function savepointName(int $depth): string
    {
        return "raxos_sp_{$depth}";
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function tableColumnExists(string $table, string $column): bool
    {
        return $this->tableExists($table) && in_array($column, $this->structure[$table], true);
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
        if ($this->structure !== null && array_key_exists($table, $this->structure)) {
            return $this->structure[$table] !== null;
        }

        $columns = $this->loadTableColumns($table);
        $this->structure ??= [];
        $this->structure[$table] = $columns;

        return $columns !== null;
    }

    /**
     * @param string $table
     *
     * @return string[]|null
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    protected function loadTableColumns(string $table): ?array
    {
        $this->structure ??= $this->loadDatabaseSchema();

        return $this->structure[$table] ?? null;
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

    /**
     * @return int[]
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    protected function getRecoverableDriverCodes(): array
    {
        return [];
    }

    /**
     * @template T
     *
     * @param callable():T $fn
     *
     * @return T
     * @throws DatabaseExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    private function runWithRecovery(callable $fn): mixed
    {
        if (!$this->autoReconnect) {
            return $fn();
        }

        try {
            return $fn();
        } catch (DatabaseExceptionInterface $err) {
            if (!$this->isRecoverableConnectionError($err)) {
                throw $err;
            }

            $this->disconnect();
            $this->connect();

            return $fn();
        }
    }

    /**
     * @param DatabaseExceptionInterface $err
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    private function isRecoverableConnectionError(DatabaseExceptionInterface $err): bool
    {
        if ($this->transactionDepth > 0) {
            return false;
        }

        $previous = $err->getPrevious();
        $info = $previous instanceof PDOException ? ($previous->errorInfo ?? null) : null;
        $driverCode = is_array($info) && isset($info[1]) ? (int)$info[1] : 0;

        if ($driverCode !== 0 && in_array($driverCode, $this->getRecoverableDriverCodes(), true)) {
            return true;
        }

        $message = strtolower($err->getMessage());

        return str_contains($message, 'gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'broken pipe');
    }

}
