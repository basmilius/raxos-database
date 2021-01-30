<?php
declare(strict_types=1);

namespace Raxos\Database\Dialect;

/**
 * Class SqlServerDialect
 *
 * @package Raxos\Database\Dialect
 */
class SqlServerDialect extends Dialect
{

    public array $escapers = ['[', ']'];

}
