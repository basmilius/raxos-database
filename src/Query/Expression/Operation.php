<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface, QueryLiteralInterface};
use Stringable;

/**
 * Class Operation
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class Operation implements QueryExpressionInterface
{

    /**
     * Operation constructor.
     *
     * @param string $operator
     * @param QueryLiteralInterface|Stringable|string|float|int $leftExpr
     * @param QueryLiteralInterface|Stringable|string|float|int $rightExpr
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $operator,
        public QueryLiteralInterface|Stringable|string|float|int $leftExpr,
        public QueryLiteralInterface|Stringable|string|float|int $rightExpr
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->compile($this->leftExpr);
        $query->raw($this->operator);
        $query->compile($this->rightExpr);
    }

}
