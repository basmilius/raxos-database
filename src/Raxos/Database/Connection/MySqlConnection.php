<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Dialect\MySqlDialect;
use Raxos\Database\Query\MySqlQuery;

/**
 * Class MySqlConnection
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
    #[Deprecated('https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows')]
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
            ->where('TABLE_SCHEMA', $this->getConnector()->getDatabase())
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
    #[Pure]
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
