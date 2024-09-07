<?php
declare(strict_types=1);

namespace Raxos\Database\Grammar;

/**
 * Class SqlServerGrammar
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Grammar
 * @since 1.1.0
 */
readonly class SqlServerGrammar extends Grammar
{

    /**
     * SqlServerGrammar constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct()
    {
        parent::__construct(
            escapers: ['[', ']']
        );
    }

}
