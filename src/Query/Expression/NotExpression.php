<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface};

/**
 * Class NotExpression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class NotExpression implements QueryExpressionInterface
{

    /**
     * NotExpression constructor.
     *
     * @param QueryExpressionInterface $expression
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryExpressionInterface $expression
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw("not ");
        $this->expression->compile($query, $connection, $grammar);
    }

}
