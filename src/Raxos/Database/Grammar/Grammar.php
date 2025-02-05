<?php
declare(strict_types=1);

namespace Raxos\Database\Grammar;

use Raxos\Database\Contract\GrammarInterface;
use Raxos\Database\Error\UnsupportedException;
use function array_map;
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

    /**
     * Grammar constructor.
     *
     * @param string[] $escapers
     * @param string $columnSeparator
     * @param string $tableSeparator
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public array $escapers = ['', ''],
        public string $columnSeparator = ', ',
        public string $tableSeparator = ', '
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compileOptimizeTable(string $table): string
    {
        throw UnsupportedException::optimizeTable();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compileTruncateTable(string $table): string
    {
        throw UnsupportedException::truncateTable();
    }

    /**
     * Escapes the column.
     *
     * @param string $value
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     * @see Grammar::realEscape()
     */
    public function escape(string $value): string
    {
        static $cache = [];

        return $cache[$value] ??= $this->realEscape($value);
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
    private function realEscape(string $value): string
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
