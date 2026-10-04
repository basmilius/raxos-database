<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\GrammarInterface;
use Raxos\Contract\Database\Query\QueryExpressionInterface;
use Raxos\Contract\Database\Query\QueryInterface;

/**
 * Class KeysetPredicate
 *
 * Builds a lexicographic boundary for a compound ascending or descending keyset.
 *
 * @internal
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 3.3.0
 */
final readonly class KeysetPredicate implements QueryExpressionInterface
{
    /**
     * Pairs ordered columns with the last row's values for the continuation boundary.
     *
     * @param list<string> $columns
     * @param list<string|int|float> $values
     * @param bool $descending
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function __construct(
        private array $columns,
        private array $values,
        private bool $descending
    )
    {
    }

    /**
     * Groups equal prefixes and the next comparison so compound ordering remains consistent.
     *
     * @param QueryInterface $query
     * @param ConnectionInterface $connection
     * @param GrammarInterface $grammar
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function compile(
        QueryInterface $query,
        ConnectionInterface $connection,
        GrammarInterface $grammar
    ): void
    {
        $query->raw('(');

        foreach ($this->columns as $index => $column) {
            if ($index > 0) {
                $query->raw('or');
            }

            $query->raw('(');

            for ($prefix = 0; $prefix < $index; ++$prefix) {
                $query->compile(new ColumnRef($this->columns[$prefix]));
                $query->raw('=');
                $query->compile($this->values[$prefix]);
                $query->raw('and');
            }

            $query->compile(new ColumnRef($column));
            $query->raw($this->descending ? '<' : '>');
            $query->compile($this->values[$index]);
            $query->raw(')');
        }

        $query->raw(')');
    }
}
