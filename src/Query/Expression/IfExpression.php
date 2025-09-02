<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryExpressionInterface, QueryValueInterface};
use Stringable;

/**
 * Class IfStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class IfExpression implements QueryExpressionInterface
{

    /**
     * IfStruct constructor.
     *
     * @param QueryInterface|QueryValueInterface|Stringable|string|float|int $expression
     * @param QueryInterface|QueryValueInterface|Stringable|string|float|int $then
     * @param QueryInterface|QueryValueInterface|Stringable|string|float|int $else
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryInterface|QueryValueInterface|Stringable|string|float|int $expression,
        public QueryInterface|QueryValueInterface|Stringable|string|float|int $then,
        public QueryInterface|QueryValueInterface|Stringable|string|float|int $else
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('if(');
        $query->compile($this->expression);
        $query->raw(',');
        $query->compile($this->then);
        $query->raw(',');
        $query->compile($this->else);
        $query->raw(')');
    }

}
