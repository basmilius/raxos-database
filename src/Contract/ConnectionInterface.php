<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;
use Raxos\Database\Db;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\Grammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Orm\Error\StructureException;

/**
 * Interface ConnectionInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.0.16
 */
interface ConnectionInterface
{

    /**
     * Returns the cache instance.
     *
     * @var CacheInterface
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public CacheInterface $cache {
        get;
    }

    /**
     * Returns TRUE if the connection is open.
     *
     * @var bool
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public bool $connected {
        get;
    }

    /**
     * Returns the DSN.
     *
     * @var string
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public string $dsn {
        get;
    }

    /**
     * Returns the used grammar of the connection.
     *
     * @var Grammar
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public Grammar $grammar {
        get;
    }

    /**
     * Returns TRUE if a transaction is active.
     *
     * @var bool
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public bool $inTransaction {
        get;
    }

    /**
     * Returns the logger.
     *
     * @var Logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public Logger $logger {
        get;
    }

    /**
     * Returns the password for the connection.
     *
     * @var string|null
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public ?string $password {
        get;
    }

    /**
     * Returns the PDO instance.
     *
     * @var PDO|null
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public ?PDO $pdo {
        get;
    }

    /**
     * Returns the username for the connection.
     *
     * @var string|null
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public ?string $username {
        get;
    }

    /**
     * Connect to the database.
     *
     * @return void
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function connect(): void;

    /**
     * Disconnect from the database.
     *
     * @return void
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function disconnect(): void;

    /**
     * Returns the given connection attribute.
     *
     * @param int $attribute
     *
     * @return mixed
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::getAttribute()
     */
    public function attribute(int $attribute): mixed;

    /**
     * Executes the given query and returns the first column of the first result.
     *
     * @param QueryInterface|string $query
     *
     * @return string|int|false
     * @throws ExecutionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::query()
     * @see PDOStatement::fetchColumn()
     */
    public function column(QueryInterface|string $query): string|int|false;

    /**
     * Executes the given query and returns the number of affected rows.
     *
     * @param QueryInterface|string $query
     *
     * @return int
     * @throws ExecutionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::exec()
     */
    public function execute(QueryInterface|string $query): int;

    /**
     * Returns the number of rows that were found in the last query.
     *
     * @return int
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function foundRows(): int;

    /**
     * Returns the last insert id.
     *
     * @param string|null $name
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::lastInsertId()
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * Returns the last insert id as an integer.
     *
     * @param string|null $name
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::lastInsertId()
     */
    public function lastInsertIdInteger(?string $name = null): int;

    /**
     * Ping the mysql server.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.8.0
     */
    public function ping(): bool;

    /**
     * Returns a new prepared statement.
     *
     * @param QueryInterface|string $query
     * @param array $options
     *
     * @return StatementInterface
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function prepare(QueryInterface|string $query, array $options = []): StatementInterface;

    /**
     * Compose a new query.
     *
     * @param bool $prepared
     *
     * @return QueryInterface
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function query(bool $prepared = true): QueryInterface;

    /**
     * Quotes the given value.
     *
     * @param BackedEnum|string|int|float|bool $value
     * @param int $type
     *
     * @return string
     * @throws ConnectionException
     * @since 1.0.16
     * @author Bas Milius <bas@mili.us>
     * @see PDO::quote()
     */
    public function quote(
        BackedEnum|string|int|float|bool $value,
        #[ExpectedValues(Db::TYPES)] int $type = PDO::PARAM_STR
    ): string;

    /**
     * Commits the active transaction.
     *
     * @return bool
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::commit()
     */
    public function commit(): bool;

    /**
     * Rolls the active transaction back.
     *
     * @return bool
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::rollBack()
     */
    public function rollBack(): bool;

    /**
     * Begin a new transaction.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @see PDO::beginTransaction()
     */
    public function transaction(): bool;

    /**
     * Loads the database schema.
     *
     * @return array<string, string[]>
     * @throws SchemaException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function loadDatabaseSchema(): array;

    /**
     * Returns TRUE if the given column exists in the given table.
     *
     * @param string $table
     * @param string $column
     *
     * @return bool
     * @throws SchemaException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function tableColumnExists(string $table, string $column): bool;

    /**
     * Returns all the columns of the given table.
     *
     * @param string $table
     *
     * @return string[]
     * @throws SchemaException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function tableColumns(string $table): array;

    /**
     * Returns TRUE if the given table exists.
     *
     * @param string $table
     *
     * @return bool
     * @throws SchemaException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function tableExists(string $table): bool;

}
