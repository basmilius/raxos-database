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
readonly class SqlServerDialect extends Dialect
{

    /**
     * SqlServerDialect constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct()
    {
        parent::__construct(
            fieldEscapers: ['[', ']']
        );
    }

}
