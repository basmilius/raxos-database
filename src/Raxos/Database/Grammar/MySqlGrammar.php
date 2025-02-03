<?php
declare(strict_types=1);

namespace Raxos\Database\Grammar;

use function sprintf;

/**
 * Class MySqlGrammar
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Grammar
 * @since 1.1.0
 */
readonly class MySqlGrammar extends Grammar
{

    /**
     * MySqlGrammar constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct()
    {
        parent::__construct(
            escapers: ['`', '`']
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 21-01-2025
     */
    public function compileOptimizeTable(string $table): string
    {
        return sprintf(
            'optimize table %s;',
            $this->escape($table)
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 21-01-2025
     */
    public function compileTruncateTable(string $table): string
    {
        return sprintf(
            'truncate table %s;',
            $this->escape($table)
        );
    }

}
