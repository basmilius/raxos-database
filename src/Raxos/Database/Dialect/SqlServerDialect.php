<?php
declare(strict_types=1);

namespace Raxos\Database\Dialect;

/**
 * Class SqlServerDialect
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Dialect
 * @since 1.0.0
 */
class SqlServerDialect extends Dialect
{

    public array $fieldEscapeCharacters = ['[', ']'];

}
