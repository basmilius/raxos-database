<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Raxos\Database\Connector\{Connector, SqlServerConnector};
use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Grammar\SqlServerGrammar;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\Cache;
use Raxos\Database\Orm\Contract\CacheInterface;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Query\SqlServerQuery;

/**
 * Class SqlServerConnection
 *
 * @property SqlServerConnector $connector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.0
 */
final class SqlServerConnection extends Connection
{

    /**
     * SqlServerConnection constructor.
     *
     * @param string $id
     * @param Connector $connector
     * @param CacheInterface $cache
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        string $id,
        Connector $connector,
        CacheInterface $cache = new Cache(),
        Logger $logger = new Logger()
    )
    {
        parent::__construct($id, $connector, $cache, new SqlServerGrammar(), $logger);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function foundRows(): int
    {
        throw QueryException::unsupported('foundRows() is not supported in SqlServer.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function loadDatabaseSchema(): array
    {
        try {
            $results = $this
                ->query(false)
                ->select(['TABLE_NAME', 'COLUMN_NAME'])
                ->from('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_CATALOG', $this->connector->database)
                ->where('TABLE_SCHEMA', $this->connector->schema)
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
     * @since 1.0.0
     */
    public function query(bool $prepared = true): QueryInterface
    {
        return new SqlServerQuery($this, $prepared);
    }

}
