<?php
declare(strict_types=1);

namespace Raxos\Database\Grammar;

/**
 * Class SQLiteGrammar
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Grammar
 * @since 1.2.0
 */
readonly class SQLiteGrammar extends Grammar
{

    /**
     * SQLiteGrammar constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.2.0
     */
    public function __construct()
    {
        parent::__construct(
            escapers: ['`', '`']
        );
    }

}
