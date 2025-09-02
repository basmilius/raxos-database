<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use BackedEnum;
use Raxos\Database\Query\Expression\SubQueryExpression;
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;

/**
 * Interface QueryExpressionsInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 2.0.0
 */
interface QueryExpressionsInterface
{

    /**
     * Returns a `between $lower and $upper` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lower
     * @param QueryLiteralInterface|Stringable|string|float|int $upper
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see BetweenExpression
     */
    public function between(
        QueryLiteralInterface|Stringable|string|float|int $lower,
        QueryLiteralInterface|Stringable|string|float|int $upper
    ): QueryExpressionInterface;

    /**
     * Returns a `coalesce(...$values)` expression.
     *
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see CoalesceExpression
     */
    public function coalesce(QueryInterface|QueryLiteralInterface|Stringable|string|float|int ...$values): QueryExpressionInterface;

    /**
     * Returns a `exists $expression` expression.
     *
     * @param QueryExpressionInterface $expression
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see ExistsExpression
     */
    public function exists(QueryExpressionInterface $expression): QueryExpressionInterface;

    /**
     * Returns `$name(...$params)` expression.
     *
     * @param string $name
     * @param array<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see FunctionExpression
     */
    public function func(string $name, array $params): QueryExpressionInterface;

    /**
     * Returns a `greatest(...$params)` expression.
     *
     * @param array<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see FunctionExpression
     * @see GreatestExpression
     */
    public function greatest(array $params): QueryExpressionInterface;

    /**
     * Returns a `group_concat([$distinct] $expression [$orderBy] [$separator] [$limit] [$offset])` expression.
     *
     * @param QueryLiteralInterface|string $expression
     * @param bool $distinct
     * @param QueryLiteralInterface|string|null $orderBy
     * @param string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see GroupConcatExpression
     */
    public function groupConcat(
        QueryLiteralInterface|string $expression,
        bool $distinct = false,
        QueryLiteralInterface|string|null $orderBy = null,
        ?string $separator = null,
        ?int $limit = null,
        ?int $offset = null
    ): QueryExpressionInterface;

    /**
     * Returns an `if($expression, $then, $else)` expression.
     *
     * @param QueryInterface|QueryValueInterface|Stringable|string|float|int $expression
     * @param QueryInterface|QueryValueInterface|Stringable|string|float|int $then
     * @param QueryInterface|QueryValueInterface|Stringable|string|float|int $else
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function if(
        QueryInterface|QueryValueInterface|Stringable|string|float|int $expression,
        QueryInterface|QueryValueInterface|Stringable|string|float|int $then,
        QueryInterface|QueryValueInterface|Stringable|string|float|int $else
    ): QueryExpressionInterface;

    /**
     * Returns a `in($values)` expression.
     *
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see InExpression
     */
    public function in(ArrayableInterface|array $values): QueryExpressionInterface;

    /**
     * Returns a `is not null` expression.
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see LiteralExpression
     */
    public function isNotNull(): QueryExpressionInterface;

    /**
     * Returns a new `is null` expression.
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see LiteralExpression
     */
    public function isNull(): QueryExpressionInterface;

    /**
     * Returns a new `match($fields) against ($expression)` expression.
     *
     * @param QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|string|float|int|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $fields
     * @param QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expression
     * @param bool $booleanMode
     * @param bool $queryExpansion
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function matchAgainst(
        QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expression,
        bool $booleanMode = false,
        bool $queryExpansion = false
    ): QueryExpressionInterface;

    /**
     * Returns a `not $expression` expression.
     *
     * @param QueryExpressionInterface $expression
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see NotExpression
     */
    public function not(QueryExpressionInterface $expression): QueryExpressionInterface;

    /**
     * Returns a `$lhs $operator $rhs` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lhs
     * @param string $operator
     * @param QueryLiteralInterface|Stringable|string|float|int $rhs
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function operation(
        QueryLiteralInterface|Stringable|string|float|int $lhs,
        string $operator,
        QueryLiteralInterface|Stringable|string|float|int $rhs
    ): QueryExpressionInterface;

    /**
     * Returns a `($subQuery)` expression.
     *
     * @param QueryInterface $query
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see SubQueryExpression
     */
    public function subQuery(QueryInterface $query): QueryExpressionInterface;

    /**
     * Returns a `@$name:= ($subQuery)` expression.
     *
     * @param string $name
     * @param SubQueryExpression $subQuery
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see VariableExpression
     * @see SubQueryExpression
     */
    public function variable(string $name, SubQueryExpression $subQuery): QueryExpressionInterface;

    /**
     * Returns a `$lhs > $rhs` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lhs
     * @param QueryLiteralInterface|Stringable|string|float|int $rhs
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function gt(
        QueryLiteralInterface|Stringable|string|float|int $lhs,
        QueryLiteralInterface|Stringable|string|float|int $rhs
    ): QueryExpressionInterface;

    /**
     * Returns a `$lhs >= $rhs` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lhs
     * @param QueryLiteralInterface|Stringable|string|float|int $rhs
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function gte(
        QueryLiteralInterface|Stringable|string|float|int $lhs,
        QueryLiteralInterface|Stringable|string|float|int $rhs
    ): QueryExpressionInterface;

    /**
     * Returns a `$lhs < $rhs` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lhs
     * @param QueryLiteralInterface|Stringable|string|float|int $rhs
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lt(
        QueryLiteralInterface|Stringable|string|float|int $lhs,
        QueryLiteralInterface|Stringable|string|float|int $rhs
    ): QueryExpressionInterface;

    /**
     * Returns a `$lhs <= $rhs` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lhs
     * @param QueryLiteralInterface|Stringable|string|float|int $rhs
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lte(
        QueryLiteralInterface|Stringable|string|float|int $lhs,
        QueryLiteralInterface|Stringable|string|float|int $rhs
    ): QueryExpressionInterface;

    /**
     * Returns a `$lhs = $rhs` expression.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lhs
     * @param QueryLiteralInterface|Stringable|string|float|int $rhs
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eq(
        QueryLiteralInterface|Stringable|string|float|int $lhs,
        QueryLiteralInterface|Stringable|string|float|int $rhs
    ): QueryExpressionInterface;

}
