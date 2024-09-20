<?php
declare(strict_types=1);

namespace Raxos\Database\Grammar;

use Raxos\Database\Contract\QueryInterface;
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
abstract readonly class Grammar
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
     * Composes an optimize table query.
     *
     * @param QueryInterface $query
     * @param string $table
     *
     * @return QueryInterface
     * @author Bas Milius <bas@mili.us>
     * @since 26-08-2024
     */
    public function composeOptimizeTable(QueryInterface $query, string $table): QueryInterface
    {
        return $query->addPiece('optimize table', $this->escape($table));
    }

    /**
     * Composes a truncate table query.
     *
     * @param QueryInterface $query
     * @param string $table
     *
     * @return QueryInterface
     * @author Bas Milius <bas@mili.us>
     * @since 26-08-2024
     */
    public function composeTruncateTable(QueryInterface $query, string $table): QueryInterface
    {
        return $query->addPiece('truncate table', $this->escape($table));
    }

    /**
     * Escapes the column.
     *
     * @param string $identifier
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function escape(string $identifier): string
    {
        if (empty($identifier)) {
            return $identifier;
        }

        if (str_contains($identifier, '(')) {
            return $identifier;
        }

        if (str_contains($identifier, '.')) {
            $identifier = explode('.', $identifier);
            $identifier = array_map($this->escape(...), $identifier);

            return implode('.', $identifier);
        }

        if (str_contains($identifier, ' ')) {
            $identifier = explode(' ', $identifier);
            $identifier[0] = $this->escape($identifier[0]);

            return implode(' ', $identifier);
        }

        if ($identifier === '*' || str_contains($identifier, $this->escapers[0])) {
            return $identifier;
        }

        return $this->escapers[0] . $identifier . $this->escapers[1];
    }

}
