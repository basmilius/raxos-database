<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Raxos\Database\Connector\Connector;
use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\SQLiteGrammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Cache;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Query\SQLiteQuery;
use function Raxos\Database\Query\literal;

/**
 * Class SQLiteConnection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.2.0
 */
final class SQLiteConnection extends Connection
{

    /**
     * SQLiteConnection constructor.
     *
     * @param string $id
     * @param Connector $connector
     * @param CacheInterface $cache
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.2.0
     */
    public function __construct(
        string $id,
        Connector $connector,
        CacheInterface $cache = new Cache(),
        Logger $logger = new Logger()
    )
    {
        parent::__construct($id, $connector, $cache, new SQLiteGrammar(), $logger);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.2.0
     */
    public function foundRows(): int
    {
        throw QueryException::unsupported('foundRows() is not supported in SQLite.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.2.0
     */
    public function loadDatabaseSchema(): array
    {
        try {
            $results = $this
                ->query(prepared: false)
                ->select(['TABLE_NAME' => 'm.name', 'COLUMN_NAME' => 'p.name'])
                ->from('sqlite_master', 'm')
                ->leftOuterJoin('pragma_table_info((m.name)) p', fn(QueryInterface $query) => $query
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
     * @since 1.2.0
     */
    public function query(bool $prepared = true): QueryInterface
    {
        return new SQLiteQuery($this, $prepared);
    }

}
