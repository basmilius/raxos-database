<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Connector\SqlServerConnector;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Dialect\SqlServerDialect;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Query\Query;
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
class SqlServerConnection extends Connection
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function foundRows(): int
    {
        throw new QueryException('foundRows() is not supported in SqlServer.', QueryException::ERR_NOT_IMPLEMENTED);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function loadDatabaseSchema(): array
    {
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
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function query(bool $isPrepared = true): Query
    {
        return new SqlServerQuery($this, $isPrepared);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    protected function initializeDialect(): Dialect
    {
        return new SqlServerDialect();
    }

}
