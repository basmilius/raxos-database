<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface};

/**
 * Class VariableExpression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class VariableExpression implements QueryExpressionInterface
{

    /**
     * VariableExpression constructor.
     *
     * @param string $name
     * @param QueryExpressionInterface $expression
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $name,
        public QueryExpressionInterface $expression
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->addPiece("@{$this->name} := ");
        $this->expression->compile($query, $connection, $grammar);
    }

}
