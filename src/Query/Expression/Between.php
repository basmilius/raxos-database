<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryLiteralInterface};
use Stringable;

/**
 * Class Between
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class Between implements QueryExpressionInterface
{

    /**
     * Between constructor.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $min
     * @param QueryLiteralInterface|Stringable|string|float|int $max
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryLiteralInterface|Stringable|string|float|int $min,
        public QueryLiteralInterface|Stringable|string|float|int $max
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('between');
        $query->compile($this->min);
        $query->raw('and');
        $query->compile($this->max);
    }

}
