<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use PDOException;
use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\GrammarInterface;
use Raxos\Contract\Database\LoggerInterface;
use Raxos\Contract\Database\Orm\CacheInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Contract\Database\Query\StatementInterface;
use Raxos\Contract\Database\TransactionalConnectionInterface;
use Raxos\Database\Db;
use Raxos\Database\Error\ExecutionException;
use Raxos\Database\Error\InvalidTableException;
use Raxos\Database\Error\NotConnectedException;
use Raxos\Database\Query\Error\NotInTransactionException;
use Raxos\Database\Query\Error\RollbackOnlyTransactionException;
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
 * Owns one driver connection, nested transaction boundaries and connection-local commit hooks.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.4.0
 */
abstract class Connection implements TransactionalConnectionInterface
{

    /**
     * Provides the native driver handle while this connection is open.
     *
     * @var ?PDO
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public protected(set) ?PDO $pdo = null;

    /**
     * Selects driver-specific schema discovery for ORM structure generation.
     *
     * @var ?array
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public private(set) ?array $structure = null;

    /**
     * Allows one reconnect after connection loss outside an active transaction.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public bool $autoReconnect = false;

    /**
     * Defers callbacks until the outer transaction commits; rollback discards them.
     *
     * @var list<callable():void>
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    private array $afterCommitCallbacks = [];

    /**
     * Tracks owned transaction levels so nested operations use matching savepoints.
     *
     * @var int
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    private int $transactionDepth = 0;

    /**
     * Prevents committing a transaction whose nested work failed without recoverable savepoints.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    private bool $transactionRollbackOnly = false;

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public bool $connected {
        get => $this->pdo !== null;
    }

    /**
     * {@inheritdoc}
     *
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
     *
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
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function column(QueryInterface|string $query): string|int|float|bool|null
    {
        $this->ensureConnected();

        if ($query instanceof QueryInterface) {
            return $this->runWithRecovery(static fn(): string|int|float|bool|null => $query->statement()->fetchColumn());
        }

        return $this->runWithRecovery(function () use ($query): string|int|float|bool|null {
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
     *
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
     *
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
     *
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
     *
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
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function prepare(
        string|QueryInterface $query,
        array $options = []
    ): StatementInterface
    {
        return new Statement($this, $query, $options);
    }

    /**
     * {@inheritdoc}
     *
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
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactionDepth = 0;
        $this->transactionRollbackOnly = false;
        $this->afterCommitCallbacks = [];
    }

    /**
     * {@inheritdoc}
     *
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
                $this->afterCommitCallbacks = [];
                $this->pdo->rollBack();

                throw new RollbackOnlyTransactionException(transactionDepth: $depth);
            }

            try {
                $committed = $this->pdo->commit();
            } catch (Throwable $error) {
                $this->afterCommitCallbacks = [];

                throw $error;
            }

            if (!$committed) {
                $this->afterCommitCallbacks = [];

                return false;
            }

            $this->transactionDepth = 0;
            $callbacks = $this->afterCommitCallbacks;
            $this->afterCommitCallbacks = [];
            $failure = null;

            foreach ($callbacks as $callback) {
                try {
                    $callback();
                } catch (Throwable $error) {
                    $failure ??= $error;
                }
            }

            if ($failure !== null) {
                throw $failure;
            }

            return true;
        }

        $name = $this->savepointName($this->transactionDepth);
        $this->pdo->exec("release savepoint {$name}");
        --$this->transactionDepth;

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function rollBack(): bool
    {
        $this->ensureConnected();

        if ($this->transactionDepth === 0 || !$this->inTransaction) {
            throw new NotInTransactionException();
        }

        $this->afterCommitCallbacks = [];

        if ($this->transactionDepth === 1) {
            $this->transactionDepth = 0;
            $this->transactionRollbackOnly = false;

            return $this->pdo->rollBack();
        }

        $name = $this->savepointName($this->transactionDepth);
        $this->pdo->exec("rollback to savepoint {$name}");
        --$this->transactionDepth;

        // A nested rollback prevents the enclosing operation from committing partial work.
        $this->transactionRollbackOnly = true;

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function transaction(): bool
    {
        $this->ensureConnected();

        if ($this->transactionDepth === 0) {
            $started = $this->pdo->beginTransaction();
            $this->transactionDepth = $started ? 1 : 0;
            $this->transactionRollbackOnly = false;
            $this->afterCommitCallbacks = [];

            return $started;
        }

        $name = $this->savepointName($this->transactionDepth + 1);
        $this->pdo->exec("savepoint {$name}");
        ++$this->transactionDepth;

        return true;
    }

    /**
     * Rolls back callback failures; a callback must leave transaction management to this method.
     *
     * @template T
     * @param callable():T $fn
     *
     * @return T
     * @throws DatabaseExceptionInterface|Throwable
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function transactional(callable $fn): mixed
    {
        $depth = $this->transactionDepth;

        if (!$this->transaction()) {
            throw new ExecutionException(new PDOException('Could not start a transaction.'));
        }

        try {
            $value = $fn();

            if ($this->transactionDepth !== $depth + 1 || !$this->inTransaction) {
                throw new ExecutionException(new PDOException('The callback changed its transaction boundary.'));
            }

            if (!$this->commit()) {
                throw new ExecutionException(new PDOException('Could not commit the transaction.'));
            }

            return $value;
        } catch (Throwable $error) {
            while ($this->transactionDepth > $depth && $this->inTransaction) {
                try {
                    $this->rollBack();
                } catch (Throwable) {
                    // Keep the callback failure as the primary exception if cleanup also fails.
                    break;
                }
            }

            throw $error;
        }
    }

    /**
     * All hooks run in registration order after the outer commit; the first hook exception is rethrown.
     *
     * @param callable():void $fn
     *
     * @return void
     * @throws Throwable
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function afterCommit(callable $fn): void
    {
        if ($this->transactionDepth === 0) {
            $fn();

            return;
        }

        $this->afterCommitCallbacks[] = $fn;
    }

    /**
     * Derives a stable savepoint identifier from the owned nested transaction level.
     *
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
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function tableColumnExists(
        string $table,
        string $column
    ): bool
    {
        return $this->tableExists($table) && in_array($column, $this->structure[$table], true);
    }

    /**
     * {@inheritdoc}
     *
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
     *
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
     * Loads column names for ORM structure generation using the active database connection.
     *
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
     * Identifies driver errors for which reconnecting outside a transaction may restore the connection.
     *
     * @return int[]
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    protected function getRecoverableDriverCodes(): array
    {
        return [];
    }

    /**
     * Retries once after a recoverable disconnect; active transactions are never replayed.
     *
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
     * Checks connection-loss codes without treating ordinary query failures as retryable.
     *
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
