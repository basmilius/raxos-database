<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Pdo;
use PDOException;
use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\MySqlGrammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Cache;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Query\MySqlQuery;
use SensitiveParameter;
use function Raxos\Database\Query\literal;

/**
 * Class MySql
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.4.0
 */
final class MySql extends Connection
{

    /**
     * MySql constructor.
     *
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param array|null $options
     * @param CacheInterface $cache
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function __construct(
        #[SensitiveParameter] string $dsn,
        #[SensitiveParameter] ?string $username = null,
        #[SensitiveParameter] ?string $password = null,
        ?array $options = null,
        CacheInterface $cache = new Cache(),
        Logger $logger = new Logger()
    )
    {
        $options += [
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        parent::__construct($dsn, $username, $password, $options, $cache, new MySqlGrammar(), $logger);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function connect(): void
    {
        try {
            $this->pdo = new Pdo\Mysql(
                $this->dsn,
                $this->username,
                $this->password,
                $this->options
            );
        } catch (PDOException $err) {
            throw ConnectionException::of($err->getCode(), $err->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function foundRows(): int
    {
        return $this->column(
            $this
                ->query(prepared: false)
                ->select(literal('found_rows()'))
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function loadDatabaseSchema(): array
    {
        try {
            $results = $this
                ->query(prepared: false)
                ->select(['TABLE_NAME', 'COLUMN_NAME'])
                ->from('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', literal('DATABASE()'))
                ->array();

            $data = [];

            foreach ($results as ['TABLE_NAME' => $table, 'COLUMN_NAME' => $column]) {
                $data[$table] ??= [];
                $data[$table][] = $column;
            }

            return $data;
        } catch (ConnectionException|ExecutionException|QueryException|RelationException|StructureException $err) {
            throw SchemaException::failed($err);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function query(bool $prepared = true): QueryInterface
    {
        return new MySqlQuery($this, $prepared);
    }

    /**
     * Creates a new MySql connection from the given options.
     *
     * @param string|null $host
     * @param int|null $port
     * @param string|null $database
     * @param string|null $unixSocket
     * @param string $charset
     * @param string|null $username
     * @param string|null $password
     * @param array|null $options
     * @param CacheInterface|null $cache
     * @param Logger|null $logger
     *
     * @return self
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public static function createFromOptions(
        #[SensitiveParameter] ?string $host = null,
        #[SensitiveParameter] ?int $port = null,
        #[SensitiveParameter] ?string $database = null,
        #[SensitiveParameter] ?string $unixSocket = null,
        #[SensitiveParameter] string $charset = 'utf8mb4',
        #[SensitiveParameter] ?string $username = null,
        #[SensitiveParameter] ?string $password = null,
        ?array $options = null,
        ?CacheInterface $cache = new Cache(),
        ?Logger $logger = new Logger()
    ): self
    {
        if ($unixSocket === null && $host === null && $port === null) {
            throw ConnectionException::missingOption('host');
        }

        if ($database === null) {
            throw ConnectionException::missingOption('database');
        }

        if ($unixSocket !== null && ($host !== null || $port !== null)) {
            throw ConnectionException::invalidOption('unixSocket', 'Either specify a unix socket or host and port');
        }

        $dsn = 'mysql:';
        $dsnParams = [];
        $dsnParams['charset'] = $charset;
        $dsnParams['dbname'] = $database;

        if ($host !== null) {
            $dsnParams['host'] = $host;
        }

        if ($port !== null) {
            $dsnParams['port'] = $port;
        }

        if ($unixSocket !== null) {
            $dsnParams['unix_socket'] = $unixSocket;
        }

        foreach ($dsnParams as $key => $value) {
            $dsn .= "$key=$value;";
        }

        return new self($dsn, $username, $password, $options, $cache, $logger);
    }

}
