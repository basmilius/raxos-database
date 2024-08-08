<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Query\QueryBaseInterface;
use Stringable;
use function is_string;

/**
 * Class BetweenComparatorAwareLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
readonly class BetweenComparatorAwareLiteral extends ComparatorAwareLiteral
{

    /**
     * BetweenComparatorAwareLiteral constructor.
     *
     * @param Stringable|Literal|string|float|int $lower
     * @param Stringable|Literal|string|float|int $upper
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        private Stringable|Literal|string|float|int $lower,
        private Stringable|Literal|string|float|int $upper
    )
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(QueryBaseInterface $query): string|int|float
    {
        $lower = $this->lower;
        $upper = $this->upper;

        if (!($lower instanceof Literal) && (is_string($lower) || $lower instanceof Stringable)) {
            $lower = $query->connection->quote((string)$lower);
        }

        if (!($upper instanceof Literal) && (is_string($upper) || $upper instanceof Stringable)) {
            $upper = $query->connection->quote((string)$upper);
        }

        return "between {$lower} and {$upper}";
    }

}
