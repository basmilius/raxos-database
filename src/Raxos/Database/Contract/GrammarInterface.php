<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use Raxos\Database\Error\UnsupportedException;

/**
 * Interface GrammarInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.5.0
 */
interface GrammarInterface
{

    public array $escapers {
        get;
    }

    /**
     * Escapes the given value.
     *
     * @param string $value
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function escape(string $value): string;

    /**
     * Compile a `optimize table $table` query.
     *
     * @param string $table
     *
     * @return string
     * @throws UnsupportedException
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compileOptimizeTable(string $table): string;

    /**
     * Compile a `truncate table $table` query.
     *
     * @param string $table
     *
     * @return string
     * @throws UnsupportedException
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compileTruncateTable(string $table): string;

}
