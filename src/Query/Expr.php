<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Contract\Collection\ArrayableInterface;
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryLiteralInterface, QueryValueInterface};
use Raxos\Database\Query\Literal\Literal;
use Stringable;
use function array_filter;
use function array_values;

/**
 * Class Expr
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.0.0
 */
final class Expr
{

    #region Comparison Operators

    /**
     * `$leftExpr = $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function eq(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('=', $leftExpr, $rightExpr);
    }

    /**
     * `$leftExpr > $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function gt(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('>', $leftExpr, $rightExpr);
    }

    /**
     * `$leftExpr >= $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function gte(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('>=', $leftExpr, $rightExpr);
    }

    /**
     * `$leftExpr < $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function lt(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('<', $leftExpr, $rightExpr);
    }

    /**
     * `$leftExpr <= $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function lte(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('<=', $leftExpr, $rightExpr);
    }

    /**
     * `is not null`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function isNotNull(): QueryExpressionInterface
    {
        return new Expression\Raw('is not null');
    }

    /**
     * `is null`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function isNull(): QueryExpressionInterface
    {
        return new Expression\Raw('is null');
    }

    /**
     * `not $expression`
     *
     * @param QueryExpressionInterface $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function not(
        QueryExpressionInterface $expr
    ): QueryExpressionInterface
    {
        return new Expression\Not($expr);
    }

    /**
     * `between $lower and $upper`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $lower
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $upper
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function between(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $lower,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $upper
    ): QueryExpressionInterface
    {
        return new Expression\Between($lower, $upper);
    }

    /**
     * `coalesce(...$values)`
     *
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function coalesce(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('coalesce', $values);
    }

    /**
     * `greatest(...$values)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function greatest(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('greatest', $values);
    }

    /**
     * `in(...$values)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function in(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('in', $values);
    }

    /**
     * `least(...$values)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function least(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface
    {
        return new Expression\Func('least', $values);
    }

    #endregion

    #region Aggregate Functions

    /**
     * `avg([$distinct] $expr)`
     *
     * @param QueryExpressionInterface|string $expr
     * @param bool $distinct
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function avg(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('avg', [$expr], distinct: $distinct);
    }

    /**
     * `count([$distinct] $expr)`
     *
     * @param QueryInterface|QueryValueInterface|string|null $expr
     * @param bool $distinct
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public static function count(
        QueryInterface|QueryValueInterface|string|null $expr = null,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        if ($expr === null) {
            return new Expression\Raw('count(*)');
        }

        return new Expression\AggregateFunc('count', [$expr], distinct: $distinct);
    }

    /**
     * `group_concat([$distinct] $expr [$orderBy] [$separator] [$limit] [$offset])`
     *
     * @param QueryValueInterface|string $expr
     * @param bool $distinct
     * @param QueryValueInterface|string|null $orderBy
     * @param string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function groupConcat(
        QueryValueInterface|string $expr,
        bool $distinct = false,
        QueryValueInterface|string|null $orderBy = null,
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
     * `max([$distinct] $expr)`
     *
     * @param QueryExpressionInterface|string $expr
     * @param bool $distinct
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function max(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('max', [$expr], distinct: $distinct);
    }

    /**
     * `min([$distinct] $expr)`
     *
     * @param QueryExpressionInterface|string $expr
     * @param bool $distinct
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function min(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('min', [$expr], distinct: $distinct);
    }

    /**
     * `sum([$distinct] $expr)`
     *
     * @param QueryExpressionInterface|string $expr
     * @param bool $distinct
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function sum(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface
    {
        return new Expression\AggregateFunc('sum', [$expr], distinct: $distinct);
    }

    #endregion

    #region Control Flow Functions

    /**
     * `if($expr, $then, $else)`
     *
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $then
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $else
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function if(
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
     * `ifnull($expr1, $expr2)`
     *
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr1
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr2
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function ifNull(
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
     * `nullif($expr1, $expr2)`
     *
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr1
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr2
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function nullIf(
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
     * `current_date()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function currentDate(): QueryExpressionInterface
    {
        return new Expression\Raw('current_date()');
    }

    /**
     * `current_time()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function currentTime(): QueryExpressionInterface
    {
        return new Expression\Raw('current_time()');
    }

    /**
     * `current_timestamp()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function currentTimestamp(): QueryExpressionInterface
    {
        return new Expression\Raw('current_timestamp()');
    }

    /**
     * `date($expr)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function date(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
    ): QueryExpressionInterface
    {
        return new Expression\Func('date', [$expr]);
    }

    /**
     * `date_add($expr, interval $value $unit)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     * @param int $value
     * @param DateTimeUnit $unit
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function dateAdd(
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
     * `date_format($expr, $format [, $locale])`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     * @param string $format
     * @param string|null $locale
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function dateFormat(
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
     * `date_sub($expr, interval $value $unit)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     * @param int $value
     * @param DateTimeUnit $unit
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function dateSub(
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
     * `datediff($expr1, $expr2)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr1
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr2
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function datediff(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface
    {
        return new Expression\Func('datediff', [$expr1, $expr2]);
    }

    /**
     * `day($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see self::dayOfMonth()
     */
    public static function day(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('day', [$date]);
    }

    /**
     * `dayname($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function dayName(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayname', [$date]);
    }

    /**
     * `dayofmonth($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see self::day()
     */
    public static function dayOfMonth(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayofmonth', [$date]);
    }

    /**
     * `dayofweek($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function dayOfWeek(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayofweek', [$date]);
    }

    /**
     * `dayofyear($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function dayOfYear(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('dayofyear', [$date]);
    }

    /**
     * `extract($unit from $date)`
     *
     * @param DateTimeUnit $unit
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function extract(
        DateTimeUnit $unit,
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Extract($unit, $date);
    }

    /**
     * `from_unixtime($unixtime [, $format])`
     *
     * @param QueryValueInterface|Stringable|string|int $unixtime
     * @param string|null $format
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function fromUnixtime(
        QueryValueInterface|Stringable|string|int $unixtime,
        ?string $format = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('from_unixtime', array_values(array_filter([$unixtime, $format], self::valueNotNull(...))));
    }

    /**
     * `hour($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function hour(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('hour', [$time]);
    }

    /**
     * `last_day($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function lastDay(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('last_day', [$date]);
    }

    /**
     * `microsecond($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function microsecond(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('microsecond', [$time]);
    }

    /**
     * `minute($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function minute(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('minute', [$time]);
    }

    /**
     * `month($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function month(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('month', [$date]);
    }

    /**
     * `monthname($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function monthname(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('monthname', [$date]);
    }

    /**
     * `now()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function now(): QueryExpressionInterface
    {
        return new Expression\Raw('now()');
    }

    /**
     * `quarter($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function quarter(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('quarter', [$date]);
    }

    /**
     * `second($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function second(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface
    {
        return new Expression\Func('second', [$time]);
    }

    /**
     * `time($expr)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function time(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
    ): QueryExpressionInterface
    {
        return new Expression\Func('time', [$expr]);
    }

    /**
     * `unix_timestamp($date)`
     *
     * @param QueryValueInterface|Stringable|string|null $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function unixTimestamp(
        QueryValueInterface|Stringable|string|null $date = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('unix_timestamp', array_values(array_filter([$date], self::valueNotNull(...))));
    }

    /**
     * `week($date [, $mode])`
     *
     * <table>
     *     <tr><th>Mode</th> <th>1st day of week</th> <th>Range</th> <th>Week 1 is the 1st week with</th></tr>
     *     <tr><td>0</td> <td>Sunday</td> <td>0-53</td> <td>a Sunday in this year</td></tr>
     *     <tr><td>1</td> <td>Monday</td> <td>0-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>2</td> <td>Sunday</td> <td>1-53</td> <td>a Sunday in this year</td></tr>
     *     <tr><td>3</td> <td>Monday</td> <td>1-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>4</td> <td>Sunday</td> <td>0-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>5</td> <td>Monday</td> <td>0-53</td> <td>a Monday in this year</td></tr>
     *     <tr><td>6</td> <td>Sunday</td> <td>1-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>7</td> <td>Monday</td> <td>1-53</td> <td>a Monday in this year</td></tr>
     * </table>
     *
     * @param QueryValueInterface|Stringable|string $date
     * @param int $mode
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function week(
        QueryValueInterface|Stringable|string $date,
        int $mode = 3
    ): QueryExpressionInterface
    {
        return new Expression\Func('week', [$date, $mode]);
    }

    /**
     * `weekday($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function weekDay(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('weekday', [$date]);
    }

    /**
     * `weekofyear($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function weekOfYear(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('weekofyear', [$date]);
    }

    /**
     * `year($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function year(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface
    {
        return new Expression\Func('year', [$date]);
    }

    /**
     * `yearweek($date [, $mode])`
     *
     * <table>
     *     <tr><th>Mode</th> <th>1st day of week</th> <th>Range</th> <th>Week 1 is the 1st week with</th></tr>
     *     <tr><td>0</td> <td>Sunday</td> <td>0-53</td> <td>a Sunday in this year</td></tr>
     *     <tr><td>1</td> <td>Monday</td> <td>0-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>2</td> <td>Sunday</td> <td>1-53</td> <td>a Sunday in this year</td></tr>
     *     <tr><td>3</td> <td>Monday</td> <td>1-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>4</td> <td>Sunday</td> <td>0-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>5</td> <td>Monday</td> <td>0-53</td> <td>a Monday in this year</td></tr>
     *     <tr><td>6</td> <td>Sunday</td> <td>1-53</td> <td>more than 3 days this year</td></tr>
     *     <tr><td>7</td> <td>Monday</td> <td>1-53</td> <td>a Monday in this year</td></tr>
     * </table>
     *
     * @param QueryValueInterface|Stringable|string $date
     * @param int $mode
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function yearWeek(
        QueryValueInterface|Stringable|string $date,
        int $mode = 3
    ): QueryExpressionInterface
    {
        return new Expression\Func('yearweek', [$date, $mode]);
    }

    #endregion

    #region Numeric Functions

    /**
     * `abs($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function abs(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('abs', [$x]);
    }

    /**
     * `acos($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function acos(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('acos', [$x]);
    }

    /**
     * `$leftExpr + $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function add(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('+', $leftExpr, $rightExpr);
    }

    /**
     * `asin($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function asin(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('asin', [$x]);
    }

    /**
     * `atan($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function atan(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('atan', [$x]);
    }

    /**
     * `atan2($x, $y)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     * @param QueryValueInterface|Stringable|string|int|float|bool $y
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function atan2(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool $y
    ): QueryExpressionInterface
    {
        return new Expression\Func('atan2', [$x, $y]);
    }

    /**
     * `ceil($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function ceil(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('ceil', [$x]);
    }

    /**
     * `ceiling($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function ceiling(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('ceiling', [$x]);
    }

    /**
     * `cos($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function cos(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('cos', [$x]);
    }

    /**
     * `cot($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function cot(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('cot', [$x]);
    }

    /**
     * `degrees($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function degrees(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('degrees', [$x]);
    }

    /**
     * `$leftExpr / $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function div(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('/', $leftExpr, $rightExpr);
    }

    /**
     * `exp($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function exp(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('exp', [$x]);
    }

    /**
     * `floor($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function floor(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('floor', [$x]);
    }

    /**
     * `ln($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function ln(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('ln', [$x]);
    }

    /**
     * `log([$b, ] $x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     * @param QueryValueInterface|Stringable|string|int|float|bool|null $b
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function log(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool|null $b = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('log', array_values(array_filter([$b, $x], self::valueNotNull(...))));
    }

    /**
     * `log10($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function log10(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('log10', [$x]);
    }

    /**
     * `log2($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function log2(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('log2', [$x]);
    }

    /**
     * `$leftExpr % $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function mod(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('%', $leftExpr, $rightExpr);
    }

    /**
     * `$leftExpr * $rightExpr`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function mul(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface
    {
        return new Expression\Operation('*', $leftExpr, $rightExpr);
    }

    /**
     * `oct($n)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $n
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function oct(
        QueryValueInterface|Stringable|string|int|float|bool $n
    ): QueryExpressionInterface
    {
        return new Expression\Func('oct', [$n]);
    }

    /**
     * `pi()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function pi(): QueryExpressionInterface
    {
        return new Expression\Raw('pi()');
    }

    /**
     * `pow($x, $y)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     * @param QueryValueInterface|Stringable|string|int|float|bool $y
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function pow(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool $y
    ): QueryExpressionInterface
    {
        return new Expression\Func('pow', [$x, $y]);
    }

    /**
     * `radians($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function radians(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('radians', [$x]);
    }

    /**
     * `rand([$n])`
     *
     * @param int|null $n
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function rand(
        ?int $n = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('rand', array_values(array_filter([$n], self::valueNotNull(...))));
    }

    /**
     * `round($x [, $d])`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     * @param int|null $d
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function round(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        ?int $d = null
    ): QueryExpressionInterface
    {
        return new Expression\Func('round', array_values(array_filter([$x, $d], self::valueNotNull(...))));
    }

    /**
     * `sign($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function sign(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('sign', [$x]);
    }

    /**
     * `sin($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function sin(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('sin', [$x]);
    }

    /**
     * `sqrt($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function sqrt(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('sqrt', [$x]);
    }

    /**
     * `tan($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function tan(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface
    {
        return new Expression\Func('tan', [$x]);
    }

    /**
     * `truncate($x, $d)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     * @param int $d
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function truncate(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        int $d
    ): QueryExpressionInterface
    {
        return new Expression\Func('truncate', [$x, $d]);
    }

    #endregion

    #region String Functions

    /**
     * `concat(...$values)`
     *
     * @param iterable<BackedEnum|QueryValueInterface|Stringable|string|int|float|bool> $values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function concat(iterable $values): QueryExpressionInterface
    {
        return new Expression\Func('concat', [...$values]);
    }

    /**
     * `concat_ws($separator, ...$values)`
     *
     * @param string $separator
     * @param iterable<BackedEnum|QueryValueInterface|Stringable|string|int|float|bool> $values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function concatWs(string $separator, iterable $values): QueryExpressionInterface
    {
        return new Expression\Func('concat_ws', [$separator, ...$values]);
    }

    /**
     * `match($fields) against ($expr)`
     *
     * @param QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface<QueryInterface|QueryValueInterface|Stringable|string|int|float>|string|float|int|array<QueryInterface|QueryValueInterface|Stringable|string|int|float> $fields
     * @param QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expr
     * @param bool $booleanMode
     * @param bool $queryExpansion
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function matchAgainst(
        QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expr,
        bool $booleanMode = false,
        bool $queryExpansion = false
    ): QueryExpressionInterface
    {
        return new Expression\MatchAgainst($fields, $expr, $booleanMode, $queryExpansion);
    }

    #endregion

    #region Case / When

    /**
     * Returns a new CASE expression builder.
     *
     * @return Expression\CaseStatement
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public static function case(): Expression\CaseStatement
    {
        return new Expression\CaseStatement();
    }

    /**
     * `when ... then ...`
     * `else ...` d
     *
     * @param QueryExpressionInterface $when
     * @param QueryExpressionInterface|QueryValueInterface $then
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public static function when(
        QueryExpressionInterface $when,
        QueryExpressionInterface|QueryValueInterface $then
    ): QueryExpressionInterface
    {
        return new Expression\When($when, $then);
    }

    #endregion

    /**
     * `exists $expr`
     *
     * @param QueryInterface|QueryExpressionInterface $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function exists(QueryInterface|QueryExpressionInterface $expr): QueryExpressionInterface
    {
        if ($expr instanceof QueryInterface) {
            $expr = self::subQuery($expr);
        }

        return new Expression\Exists($expr);
    }

    /**
     * `$name(...$params)`
     *
     * @param string $name
     * @param array<BackedEnum|QueryValueInterface|Stringable|string|int|float|bool> $params
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function func(string $name, array $params): QueryExpressionInterface
    {
        return new Expression\Func($name, $params);
    }

    /**
     * `sha1($value)`
     *
     * @param QueryValueInterface|Stringable|string|int|float $value
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function sha1(QueryValueInterface|Stringable|string|int|float $value): QueryExpressionInterface
    {
        return new Expression\Func('sha1', [$value]);
    }

    /**
     * `($subQuery)`
     *
     * @param QueryInterface $query
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function subQuery(QueryInterface $query): QueryExpressionInterface
    {
        return new Expression\SubQuery($query);
    }

    /**
     * `@$name:= ($subQuery)`
     *
     * @param string $name
     * @param QueryInterface|Expression\SubQuery $subQuery
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function variable(string $name, QueryInterface|Expression\SubQuery $subQuery): QueryExpressionInterface
    {
        if ($subQuery instanceof QueryInterface) {
            $subQuery = self::subQuery($subQuery);
        }

        return new Expression\Variable($name, $subQuery);
    }

    /**
     * Returns true if the given value is not null.
     *
     * @param mixed $value
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    private static function valueNotNull(mixed $value): bool
    {
        return $value !== null;
    }

}
