<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Database\Contract\{QueryExpressionInterface, QueryExpressionsInterface, QueryInterface, QueryLiteralInterface, QueryValueInterface};
use Raxos\Database\Query\Literal\Literal;
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;
use function array_filter;

/**
 * Class Expression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.0.0
 */
final class Expr implements QueryExpressionsInterface
{

    #region Comparison Operators

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eq(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('=', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function gt(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('>', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function gte(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('>=', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lt(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('<', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lte(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('<=', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function isNotNull(): QueryExpressionInterface
    {
        return new Expression\Raw('is not null');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function isNull(): QueryExpressionInterface
    {
        return new Expression\Raw('is null');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function not(
        QueryExpressionInterface $expr
    ): QueryExpressionInterface
    {
        return new Expression\Not($expr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function between(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $lower,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $upper
    ): QueryExpressionInterface
    {
        return new Expression\Between($lower, $upper);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function coalesce(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('coalesce', $values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function greatest(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('greatest', $values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function in(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('in', $values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function least(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('least', $values);
    }

    #endregion

    #region Aggregate Functions

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function avg(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('avg', [$expr], distinct: $distinct);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function groupConcat(
        QueryLiteralInterface|string $expr,
        bool $distinct = false,
        QueryLiteralInterface|string|null $orderBy = null,
        ?string $separator = null,
        ?int $limit = null,
        ?int $offset = null
    ): QueryExpressionInterface
    {
        return new Expression\GroupConcat(
            $expr,
            distinct: $distinct,
            orderBy: $orderBy,
            separator: $separator,
            limit: $limit,
            offset: $offset
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function max(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('max', [$expr], distinct: $distinct);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function min(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('min', [$expr], distinct: $distinct);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sum(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('sum', [$expr], distinct: $distinct);
    }

    #endregion

    #region Control Flow Functions

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function if(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $then,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $else
    ): QueryExpressionInterface
    {
        return new Expression\Func('if', [
            $expr,
            $then,
            $else
        ]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ifNull(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface
    {
        return new Expression\Func('ifnull', [
            $expr1,
            $expr2
        ]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function nullIf(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface
    {
        return new Expression\Func('nullif', [
            $expr1,
            $expr2
        ]);
    }

    #endregion

    #region Date & Time Functions

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function currentDate(): QueryExpressionInterface
    {
        return new Expression\Raw('current_date()');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function currentTime(): QueryExpressionInterface
    {
        return new Expression\Raw('current_time()');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function currentTimestamp(): QueryExpressionInterface
    {
        return new Expression\Raw('current_timestamp()');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function date(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
    ): QueryExpressionInterface
    {
        return new Expression\Func('date', [$expr]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dateAdd(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        int $value,
        DateTimeUnit $unit
    ): QueryExpressionInterface
    {
        return new Expression\Func('date_add', [
            $expr,
            Literal::string("interval {$value} {$unit->value}")
        ]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dateFormat(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        string $format,
        ?string $locale = null
    ): QueryExpressionInterface
    {
        $params = [
            $expr,
            Literal::string($format)
        ];

        if ($locale !== null) {
            $params[] = Literal::string($locale);
        }

        return new Expression\Func('date_format', $params);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dateSub(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        int $value,
        DateTimeUnit $unit
    ): QueryExpressionInterface
    {
        return new Expression\Func('date_sub', [
            $expr,
            Literal::string("interval {$value} {$unit->value}")
        ]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function datediff(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface
    {
        return new Expression\Func('datediff', [$expr1, $expr2]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function day(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('day', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayName(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayname', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayOfMonth(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayofmonth', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayOfWeek(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayofweek', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayOfYear(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayofyear', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function extract(
        DateTimeUnit $unit,
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Extract($unit, $date);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function fromUnixtime(
        QueryValueInterface|Stringable|string|int $unixtime,
        ?string $format = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('from_unixtime', array_filter([$unixtime, $format]));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function hour(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('hour', [$time]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lastDay(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('last_day', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function microsecond(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('microsecond', [$time]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function minute(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('minute', [$time]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function month(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('month', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function monthname(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('monthname', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function now(): QueryExpressionInterface
    {
        return new Expression\Raw('now()');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function quarter(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('quarter', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function second(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('second', [$time]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function time(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
    ): QueryExpressionInterface
    {
        return new Expression\Func('time', [$expr]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function unixTimestamp(
        QueryValueInterface|Stringable|string|null $date = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('unixtimestamp', array_filter([$date]));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function week(
        QueryValueInterface|Stringable|string $date,
        int $mode = 3
    ): QueryExpressionInterface
    {
        return new Expression\Func('week', [$date, $mode]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function weekDay(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('weekday', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function weekOfYear(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('weekofyear', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function year(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('year', [$date]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function yearWeek(
        QueryValueInterface|Stringable|string $date,
        int $mode = 3
    ): QueryExpressionInterface
    {
        return new Expression\Func('yearweek', [$date, $mode]);
    }

    #endregion

    #region Numeric Functions

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function abs(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('abs', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function acos(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('acos', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function add(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('+', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function asin(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('asin', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function atan(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('atan', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function atan2(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool $y
    ): QueryExpressionInterface
    {
        return new Expression\Func('atan2', [$x, $y]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ceil(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('ceil', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ceiling(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('ceiling', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function cos(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('cos', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function cot(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('cot', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function degrees(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('degrees', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function div(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('/', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function exp(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('exp', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function floor(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('floor', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ln(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('ln', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function log(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool|null $b = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('log', array_filter([$b, $x]));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function log10(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('log10', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function log2(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('log2', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function mod(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('%', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function mul(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('*', $leftExpr, $rightExpr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function oct(
        QueryValueInterface|Stringable|string|int|float|bool $n
    ): QueryExpressionInterface
    {
        return new Expression\Func('oct', [$n]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function pi(): QueryExpressionInterface
    {
        return new Expression\Raw('pi()');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function pow(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool $y
    ): QueryExpressionInterface
    {
        return new Expression\Func('pow', [$x, $y]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function radians(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('radians', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function rand(
        ?int $n = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('rand', array_filter([$n]));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function round(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        ?int $d = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('round', array_filter([$x, $d]));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sign(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('sign', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sin(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('sin', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sqrt(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('sqrt', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function tan(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('tan', [$x]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function truncate(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        int $d
    ): QueryExpressionInterface
    {
        return new Expression\Func('truncate', [$x, $d]);
    }

    #endregion

    #region String Functions

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function concat(iterable $values): QueryExpressionInterface
    {
        return new Expression\Func('concat', [$values]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function concatWs(string $separator, iterable $values): QueryExpressionInterface
    {
        return new Expression\Func('concat_ws', [$separator, ...$values]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function matchAgainst(
        QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expr,
        bool $booleanMode = false,
        bool $queryExpansion = false
    ): QueryExpressionInterface
    {
        return new Expression\MatchAgainst($fields, $expr, $booleanMode, $queryExpansion);
    }

    #endregion

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function exists(QueryExpressionInterface $expr): QueryExpressionInterface
    {
        return new Expression\Exists($expr);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function func(string $name, array $params): QueryExpressionInterface
    {
        return new Expression\Func($name, $params);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sha1(QueryValueInterface|Stringable|string|int|float $value): QueryExpressionInterface
    {
        return new Expression\Func('sha1', [$value]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function subQuery(QueryInterface $query): QueryExpressionInterface
    {
        return new Expression\SubQuery($query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function variable(string $name, Expression\SubQuery $subQuery): QueryExpressionInterface
    {
        return new Expression\Variable($name, $subQuery);
    }

}
