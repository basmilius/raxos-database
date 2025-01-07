<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\SQLiteGrammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Cache;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Query\SQLiteQuery;
use SensitiveParameter;
use function Raxos\Database\Query\literal;

/**
 * Class SQLite
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.4.0
 */
final class SQLite extends Connection
{

    /**
     * SQLite constructor.
     *
     * @param string $dsn
     * @param array|null $options
     * @param CacheInterface $cache
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function __construct(
        #[SensitiveParameter] string $dsn,
        ?array $options = null,
        CacheInterface $cache = new Cache(),
        Logger $logger = new Logger()
    )
    {
        parent::__construct($dsn, null, null, $options, $cache, new SQLiteGrammar(), $logger);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function connect(): void
    {
        $this->pdo = new \Pdo\Sqlite(
            $this->dsn,
            options: $this->options
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public function foundRows(): int
    {
        throw QueryException::unsupported('foundRows() is not supported in SQLite.');
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
                ->select(['TABLE_NAME' => 'm.name', 'COLUMN_NAME' => 'p.name'])
                ->from('sqlite_master', 'm')
                ->leftOuterJoin('pragma_table_info((m.name)) p', static fn(QueryInterface $query) => $query
                    ->on('m.name', '!=', literal('p.name')))
                ->orderBy(['TABLE_NAME', 'p.cid'])
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
        return new SQLiteQuery($this, $prepared);
    }

    /**
     * Creates a new sqlite connection from a file.
     *
     * @param string $path
     * @param array|null $options
     * @param CacheInterface|null $cache
     * @param Logger|null $logger
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public static function createFromFile(
        string $path,
        ?array $options = null,
        ?CacheInterface $cache = new Cache(),
        ?Logger $logger = new Logger()
    ): self
    {
        return new self(
            "sqlite:$path",
            $options,
            $cache,
            $logger
        );
    }

    /**
     * Creates a new in-memory sqlite connection.
     *
     * @param array|null $options
     * @param CacheInterface|null $cache
     * @param Logger|null $logger
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.4.0
     */
    public static function createFromInMemory(
        ?array $options = null,
        ?CacheInterface $cache = new Cache(),
        ?Logger $logger = new Logger()
    ): self
    {
        return new self(
            'sqlite::memory:',
            $options,
            $cache,
            $logger
        );
    }

}
