<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

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
    public function foundRows(): int
    {
        return $this->queryColumn(
            $this->query(false)
                ->select('found_rows()')
        );
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
    protected function initializeDialect(): Dialect
    {
        return new MySqlDialect();
    }

}
