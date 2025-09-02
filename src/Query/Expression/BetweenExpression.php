<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryExpressionInterface};
use Stringable;

/**
 * Class BetweenExpression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class BetweenExpression implements QueryExpressionInterface
{

    /**
     * BetweenExpression constructor.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lower
     * @param QueryLiteralInterface|Stringable|string|float|int $upper
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryLiteralInterface|Stringable|string|float|int $lower,
        public QueryLiteralInterface|Stringable|string|float|int $upper
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('between');
        $query->compile($this->lower);
        $query->raw('and');
        $query->compile($this->upper);
    }

}
