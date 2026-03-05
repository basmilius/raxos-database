<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryValueInterface};
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
     * @param BackedEnum|QueryValueInterface|Stringable|string|float|int $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|float|int $rightExpr
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $operator,
        public BackedEnum|QueryValueInterface|Stringable|string|float|int $leftExpr,
        public BackedEnum|QueryValueInterface|Stringable|string|float|int $rightExpr
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
