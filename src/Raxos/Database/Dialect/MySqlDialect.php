<?php
declare(strict_types=1);

namespace Raxos\Database\Dialect;

use Raxos\Database\Query\Query;

/**
 * Class MySqlDialect
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Dialect
 * @since 1.0.0
 */
class MySqlDialect extends Dialect
{

    public array $escapers = ['`', '`'];

}
