<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\{QueryExpressionInterface, QueryExpressionsInterface, QueryInterface, QueryLiteralInterface, QueryValueInterface};
use Raxos\Database\Query\Expression as Expression;
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;

/**
 * Class Expression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.0.0
 */
final class Expr implements QueryExpressionsInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function between(
        QueryValueInterface|Stringable|string|int|float $lower,
        QueryValueInterface|Stringable|string|int|float $upper
    ): QueryExpressionInterface
    {
        return new Expression\BetweenExpression($lower, $upper);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function coalesce(QueryInterface|QueryValueInterface|Stringable|string|int|float ...$values): QueryExpressionInterface
    {
        return new Expression\CoalesceExpression($values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function concat(iterable $values): QueryExpressionInterface
    {
        return new Expression\ConcatExpression($values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function concatWs(string $separator, iterable $values): QueryExpressionInterface
    {
        return new Expression\ConcatWsExpression($separator, $values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function exists(QueryExpressionInterface $expression): QueryExpressionInterface
    {
        return new Expression\ExistsExpression($expression);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function func(string $name, array $params): QueryExpressionInterface
    {
        return new Expression\FunctionExpression($name, $params);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function greatest(array $params): QueryExpressionInterface
    {
        return new Expression\GreatestExpression($params);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function groupConcat(
        QueryLiteralInterface|string $expression,
        bool $distinct = false,
        QueryLiteralInterface|string|null $orderBy = null,
        ?string $separator = null,
        ?int $limit = null,
        ?int $offset = null
    ): QueryExpressionInterface
    {
        return new Expression\GroupConcatExpression($expression, $distinct, $orderBy, $separator, $limit, $offset);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function if(
        QueryInterface|QueryValueInterface|Stringable|string|int|float $expression,
        QueryInterface|QueryValueInterface|Stringable|string|int|float $then,
        QueryInterface|QueryValueInterface|Stringable|string|int|float $else
    ): QueryExpressionInterface
    {
        return new Expression\IfExpression($expression, $then, $else);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function in(ArrayableInterface|array $values): QueryExpressionInterface
    {
        return new Expression\InExpression($values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function isNotNull(): QueryExpressionInterface
    {
        return new Expression\LiteralExpression('is not null');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function isNull(): QueryExpressionInterface
    {
        return new Expression\LiteralExpression('is null');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function matchAgainst(
        QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expression,
        bool $booleanMode = false,
        bool $queryExpansion = false
    ): QueryExpressionInterface
    {
        return new Expression\MatchAgainstExpression($fields, $expression, $booleanMode, $queryExpansion);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function not(QueryExpressionInterface $expression): QueryExpressionInterface
    {
        return new Expression\NotExpression($expression);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function operation(
        QueryValueInterface|Stringable|string|int|float $lhs,
        string $operator,
        QueryValueInterface|Stringable|string|int|float $rhs
    ): QueryExpressionInterface
    {
        return new Expression\OperationExpression($operator, $lhs, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sha1(QueryValueInterface|Stringable|string|int|float $value): QueryExpressionInterface
    {
        return new Expression\FunctionExpression('sha1', [$value]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function subQuery(QueryInterface $query): QueryExpressionInterface
    {
        return new Expression\SubQueryExpression($query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function variable(string $name, Expression\SubQueryExpression $subQuery): QueryExpressionInterface
    {
        return new Expression\VariableExpression($name, $subQuery);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function gt(QueryValueInterface|Stringable|string|int|float $lhs, QueryValueInterface|Stringable|string|int|float $rhs): QueryExpressionInterface
    {
        return $this->operation($lhs, '>', $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function gte(QueryValueInterface|Stringable|string|int|float $lhs, QueryValueInterface|Stringable|string|int|float $rhs): QueryExpressionInterface
    {
        return $this->operation($lhs, '>=', $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lt(QueryValueInterface|Stringable|string|int|float $lhs, QueryValueInterface|Stringable|string|int|float $rhs): QueryExpressionInterface
    {
        return $this->operation($lhs, '<', $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lte(QueryValueInterface|Stringable|string|int|float $lhs, QueryValueInterface|Stringable|string|int|float $rhs): QueryExpressionInterface
    {
        return $this->operation($lhs, '<=', $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eq(QueryValueInterface|Stringable|string|int|float $lhs, QueryValueInterface|Stringable|string|int|float $rhs): QueryExpressionInterface
    {
        return $this->operation($lhs, '=', $rhs);
    }

}
