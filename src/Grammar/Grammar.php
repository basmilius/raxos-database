<?php
declare(strict_types=1);

namespace Raxos\Database\Grammar;

use Raxos\Contract\Database\GrammarInterface;
use Raxos\Database\Query\Error\UnsupportedException;
use function array_map;
use function count;
use function explode;
use function implode;
use function str_contains;

/**
 * Class Grammar
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Grammar
 * @since 1.1.0
 */
abstract readonly class Grammar implements GrammarInterface
{

    private const int ESCAPE_CACHE_LIMIT = 4096;

    /**
     * Grammar constructor.
     *
     * @param string[] $escapers
     * @param string $columnSeparator
     * @param string $tableSeparator
     * @param bool $supportsReturning
     * @param bool $supportsRowValueConstructors
     * @param bool $supportsRowLocking
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public array $escapers = ['', ''],
        public string $columnSeparator = ', ',
        public string $tableSeparator = ', ',
        public bool $supportsReturning = true,
        public bool $supportsRowValueConstructors = true,
        public bool $supportsRowLocking = false
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileForShare(): string
    {
        throw new UnsupportedException('Feature forShare is not supported by the current database engine.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileForUpdate(): string
    {
        throw new UnsupportedException('Feature forUpdate is not supported by the current database engine.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileLockNowait(): string
    {
        throw new UnsupportedException('Feature nowait is not supported by the current database engine.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function compileLockSkipLocked(): string
    {
        throw new UnsupportedException('Feature skipLocked is not supported by the current database engine.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compileOptimizeTable(string $table): string
    {
        throw new UnsupportedException('Feature optimizeTable is not supported by the current database engine.');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compileTruncateTable(string $table): string
    {
        throw new UnsupportedException('Feature truncateTable is not supported by the current database engine.');
    }

    /**
     * Escapes the given identifier.
     *
     * @param string $value
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function escape(string $value): string
    {
        static $cache = [];

        $key = static::class . ':' . $value;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if (count($cache) >= self::ESCAPE_CACHE_LIMIT) {
            $cache = [];
        }

        return $cache[$key] = $this->escapeImpl($value);
    }

    /**
     * Real implementation of {@see Grammar::escape()}.
     *
     * @param string $value
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.2.0
     */
    private function escapeImpl(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        if (str_contains($value, '(')) {
            return $value;
        }

        if (str_contains($value, '.')) {
            $value = explode('.', $value);
            $value = array_map($this->escape(...), $value);

            return implode('.', $value);
        }

        if (str_contains($value, ' ')) {
            $value = explode(' ', $value);
            $value[0] = $this->escape($value[0]);

            return implode(' ', $value);
        }

        if ($value === '*' || str_contains($value, $this->escapers[0])) {
            return $value;
        }

        return $this->escapers[0] . $value . $this->escapers[1];
    }

}
