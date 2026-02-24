<?php
declare(strict_types=1);

namespace Raxos\Database\Migration;

use Raxos\Contract\Database\ConnectionInterface;

/**
 * Class Migration
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Migration
 * @since 1.9.0
 */
abstract class Migration
{

    /**
     * Applies the migration to the database.
     *
     * @param ConnectionInterface $connection
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    abstract public function up(ConnectionInterface $connection): void;

}
