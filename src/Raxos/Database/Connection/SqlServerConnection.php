<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Dialect\SqlServerDialect;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\SqlServerQuery;

/**
 * Class SqlServerConnection
 *
 * @package Raxos\Database\Connection
 */
class SqlServerConnection extends Connection
{

    /**
     * {@inheritDoc}
     * @throws QueryException
     */
    public function foundRows(): int
    {
        throw new QueryException('foundRows is not supported in SqlServer.');
    }

    /**
     * {@inheritdoc}
     */
    public function loadDatabaseSchema(): array
    {
        $results = $this
            ->query(false)
            ->select(['TABLE_NAME', 'COLUMN_NAME'])
            ->from('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_CATALOG', $this->getConnector()->getDatabase())
            ->where('TABLE_SCHEMA', $this->getConnector()->getSchema())
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
     */
    #[Pure]
    public function query(bool $isPrepared = true): Query
    {
        return new SqlServerQuery($this, $isPrepared);
    }

    /**
     * {@inheritDoc}
     */
    #[Pure]
    protected function initializeDialect(): Dialect
    {
        return new SqlServerDialect();
    }
}
