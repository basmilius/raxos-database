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
    public function __construct(
        array $escapers = ['`', '`'],
        bool $supportsReturning = false
    )
    {
        parent::__construct(
            escapers: $escapers,
            supportsReturning: $supportsReturning,
            supportsRowLocking: true
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileForShare(): string
    {
        return 'for share';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileForUpdate(): string
    {
        return 'for update';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileLockNowait(): string
    {
        return 'nowait';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileLockSkipLocked(): string
    {
        return 'skip locked';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
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
     * @since 1.5.0
     */
    public function compileTruncateTable(string $table): string
    {
        return sprintf(
            'truncate table %s;',
            $this->escape($table)
        );
    }

}
