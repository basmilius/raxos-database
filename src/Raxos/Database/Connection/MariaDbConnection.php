<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Dialect\MariaDbDialect;
use Raxos\Database\Query\MariaDbQuery;

/**
 * Class MariaDbConnection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.0
 */
class MariaDbConnection extends MySqlConnection
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function query(bool $isPrepared = true): MariaDbQuery
    {
        return new MariaDbQuery($this, $isPrepared);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function initializeDialect(): Dialect
    {
        return new MariaDbDialect();
    }

}
