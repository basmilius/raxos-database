<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Connector\MySqlConnector;
use Raxos\Database\Dialect\{Dialect, MySqlDialect};
use Raxos\Database\Query\MySqlQuery;

/**
 * Class MySqlConnection
 *
 * @property MySqlConnector $connector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.0
 */
class MySqlConnection extends Connection
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function foundRows(): int
    {
        return $this->column(
            $this->query(false)
                ->select('found_rows()')
        );
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
            ->from('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->connector->database)
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
    public function query(bool $isPrepared = true): MySqlQuery
    {
        return new MySqlQuery($this, $isPrepared);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    protected function initializeDialect(): Dialect
    {
        return new MySqlDialect();
    }

}
