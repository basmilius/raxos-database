<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Dialect\{Dialect, MariaDbDialect};
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
    public function query(bool $prepared = true): MariaDbQuery
    {
        return new MariaDbQuery($this, $prepared);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    protected function initializeDialect(): Dialect
    {
        return new MariaDbDialect();
    }

}
