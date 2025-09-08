<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use BackedEnum;
use Raxos\Database\Query\{DateTimeUnit, Expression};
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
    public function eq(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

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
    public function gt(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

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
    public function gte(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

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
    public function lt(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

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
    public function lte(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

    /**
     * `is not null`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function isNotNull(): QueryExpressionInterface;

    /**
     * `is null`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function isNull(): QueryExpressionInterface;

    /**
     * `not $expression`
     *
     * @param QueryExpressionInterface $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function not(
        QueryExpressionInterface $expr
    ): QueryExpressionInterface;

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
    public function between(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $lower,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $upper
    ): QueryExpressionInterface;

    /**
     * `coalesce(...$values)`
     *
     * @param BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function coalesce(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface;

    /**
     * `greatest(...$values)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function greatest(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface;

    /**
     * `in(...$values)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function in(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface;

    /**
     * `least(...$values)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function least(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool ...$values
    ): QueryExpressionInterface;

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
    public function avg(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface;

    /**
     * `group_concat([$distinct] $expr [$orderBy] [$separator] [$limit] [$offset])`
     *
     * @param QueryLiteralInterface|string $expr
     * @param bool $distinct
     * @param QueryLiteralInterface|string|null $orderBy
     * @param string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return QueryExpressionInterface
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
    ): QueryExpressionInterface;

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
    public function max(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface;

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
    public function min(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface;

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
    public function sum(
        QueryExpressionInterface|string $expr,
        bool $distinct = false
    ): QueryExpressionInterface;

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
    public function if(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $then,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $else
    ): QueryExpressionInterface;

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
    public function ifNull(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface;

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
    public function nullIf(
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryInterface|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface;

    #endregion

    #region Date & Time Functions

    /**
     * `current_date()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function currentDate(): QueryExpressionInterface;

    /**
     * `current_time()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function currentTime(): QueryExpressionInterface;

    /**
     * `current_timestamp()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function currentTimestamp(): QueryExpressionInterface;

    /**
     * `date($expr)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function date(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
    ): QueryExpressionInterface;

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
    public function dateAdd(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        int $value,
        DateTimeUnit $unit
    ): QueryExpressionInterface;

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
    public function dateFormat(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        string $format,
        ?string $locale = null
    ): QueryExpressionInterface;

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
    public function dateSub(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        int $value,
        DateTimeUnit $unit
    ): QueryExpressionInterface;

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
    public function datediff(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr1,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr2
    ): QueryExpressionInterface;

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
    public function day(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `dayname($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayName(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

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
    public function dayOfMonth(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `dayofweek($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayOfWeek(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `dayofyear($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function dayOfYear(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

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
    public function extract(
        DateTimeUnit $unit,
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

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
    public function fromUnixtime(
        QueryValueInterface|Stringable|string|int $unixtime,
        ?string $format = null
    ): QueryExpressionInterface;

    /**
     * `hour($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function hour(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface;

    /**
     * `last_day($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function lastDay(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `microsecond($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function microsecond(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface;

    /**
     * `minute($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function minute(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface;

    /**
     * `month($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function month(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `monthname($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function monthname(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `now()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function now(): QueryExpressionInterface;

    /**
     * `quarter($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function quarter(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `second($time)`
     *
     * @param QueryValueInterface|Stringable|string $time
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function second(
        QueryValueInterface|Stringable|string $time
    ): QueryExpressionInterface;

    /**
     * `time($expr)`
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function time(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
    ): QueryExpressionInterface;

    /**
     * `unix_timestamp($date)`
     *
     * @param QueryValueInterface|Stringable|string|null $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function unixTimestamp(
        QueryValueInterface|Stringable|string|null $date = null
    ): QueryExpressionInterface;

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
    public function week(
        QueryValueInterface|Stringable|string $date,
        int $mode = 3
    ): QueryExpressionInterface;

    /**
     * `weekday($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function weekDay(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `weekofyear($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function weekOfYear(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

    /**
     * `year($date)`
     *
     * @param QueryValueInterface|Stringable|string $date
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function year(
        QueryValueInterface|Stringable|string $date
    ): QueryExpressionInterface;

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
    public function yearWeek(
        QueryValueInterface|Stringable|string $date,
        int $mode = 3
    ): QueryExpressionInterface;

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
    public function abs(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `acos($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function acos(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

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
    public function add(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

    /**
     * `asin($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function asin(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `atan($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function atan(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

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
    public function atan2(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool $y
    ): QueryExpressionInterface;

    /**
     * `ceil($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ceil(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `ceiling($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ceiling(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `cos($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function cos(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `cot($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function cot(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `degrees($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function degrees(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

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
    public function div(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

    /**
     * `exp($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function exp(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `floor($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function floor(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `ln($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function ln(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

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
    public function log(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool|null $b = null
    ): QueryExpressionInterface;

    /**
     * `log10($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function log10(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `log2($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function log2(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

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
    public function mod(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

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
    public function mul(
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $leftExpr,
        BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $rightExpr
    ): QueryExpressionInterface;

    /**
     * `oct($n)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $n
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function oct(
        QueryValueInterface|Stringable|string|int|float|bool $n
    ): QueryExpressionInterface;

    /**
     * `pi()`
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function pi(): QueryExpressionInterface;

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
    public function pow(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        QueryValueInterface|Stringable|string|int|float|bool $y
    ): QueryExpressionInterface;

    /**
     * `radians($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function radians(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `rand([$n])`
     *
     * @param int|null $n
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function rand(
        ?int $n = null
    ): QueryExpressionInterface;

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
    public function round(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        ?int $d = null
    ): QueryExpressionInterface;

    /**
     * `sign($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sign(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `sin($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sin(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `sqrt($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sqrt(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

    /**
     * `tan($x)`
     *
     * @param QueryValueInterface|Stringable|string|int|float|bool $x
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function tan(
        QueryValueInterface|Stringable|string|int|float|bool $x
    ): QueryExpressionInterface;

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
    public function truncate(
        QueryValueInterface|Stringable|string|int|float|bool $x,
        int $d
    ): QueryExpressionInterface;

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
    public function concat(
        iterable $values
    ): QueryExpressionInterface;

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
    public function concatWs(
        string $separator,
        iterable $values
    ): QueryExpressionInterface;

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
    public function matchAgainst(
        QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expr,
        bool $booleanMode = false,
        bool $queryExpansion = false
    ): QueryExpressionInterface;

    #endregion

    /**
     * `exists $expr`
     *
     * @param QueryExpressionInterface $expr
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function exists(QueryExpressionInterface $expr): QueryExpressionInterface;

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
    public function func(string $name, array $params): QueryExpressionInterface;

    /**
     * `sha1($value)`
     *
     * @param QueryValueInterface|Stringable|string|int|float $value
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function sha1(QueryValueInterface|Stringable|string|int|float $value): QueryExpressionInterface;

    /**
     * `($subQuery)`
     *
     * @param QueryInterface $query
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function subQuery(QueryInterface $query): QueryExpressionInterface;

    /**
     * `@$name:= ($subQuery)`
     *
     * @param string $name
     * @param Expression\SubQuery $subQuery
     *
     * @return QueryExpressionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function variable(string $name, Expression\SubQuery $subQuery): QueryExpressionInterface;

}
