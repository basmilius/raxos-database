<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Database\Contract\{QueryInterface, QueryLiteralInterface, QueryStructInterface, QueryValueInterface};
use Raxos\Database\Query\Struct\{BetweenStruct, CoalesceStruct, ExistsStruct, FunctionStruct, GreatestStruct, GroupConcatStruct, IfStruct, InStruct, LiteralStruct, MatchAgainstStruct, NotStruct, SubQueryStruct, VariableStruct};
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;

/**
 * Class Struct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.5.0
 */
final class Struct
{

    /**
     * Returns a `between $lower and $upper` struct.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lower
     * @param QueryLiteralInterface|Stringable|string|float|int $upper
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see BetweenStruct
     */
    public static function between(
        QueryLiteralInterface|Stringable|string|float|int $lower,
        QueryLiteralInterface|Stringable|string|float|int $upper
    ): QueryStructInterface
    {
        return new BetweenStruct($lower, $upper);
    }

    /**
     * Returns a `coalesce(...$values)` struct.
     *
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int ...$values
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see CoalesceStruct
     */
    public static function coalesce(QueryInterface|QueryLiteralInterface|Stringable|string|float|int ...$values): QueryStructInterface
    {
        return new CoalesceStruct($values);
    }

    /**
     * Returns a `exists $struct` struct.
     *
     * @param QueryStructInterface $struct
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see ExistsStruct
     */
    public static function exists(QueryStructInterface $struct): QueryStructInterface
    {
        return new ExistsStruct($struct);
    }

    /**
     * Returns `$name(...$params)` struct.
     *
     * @param string $name
     * @param array<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see FunctionStruct
     */
    public static function function (string $name, array $params): QueryStructInterface
    {
        return new FunctionStruct($name, $params);
    }

    /**
     * Returns a `greatest(...$params)` struct.
     *
     * @param array<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     * @see FunctionStruct
     * @see GreatestStruct
     */
    public static function greatest(array $params): QueryStructInterface
    {
        return new GreatestStruct($params);
    }

    /**
     * Returns a `group_concat([$distinct] $expression [$orderBy] [$separator] [$limit] [$offset])` struct.
     *
     * @param QueryLiteralInterface|string $expression
     * @param bool $distinct
     * @param QueryLiteralInterface|string|null $orderBy
     * @param string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see GroupConcatStruct
     */
    public static function groupConcat(
        QueryLiteralInterface|string $expression,
        bool $distinct = false,
        QueryLiteralInterface|string|null $orderBy = null,
        ?string $separator = null,
        ?int $limit = null,
        ?int $offset = null
    ): QueryStructInterface
    {
        return new GroupConcatStruct($expression, $distinct, $orderBy, $separator, $limit, $offset);
    }

    /**
     * Returns an `if($expression, $then, $else)` struct.
     *
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int $expression
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int $then
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int $else
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public static function if(
        QueryInterface|QueryLiteralInterface|Stringable|string|float|int $expression,
        QueryInterface|QueryLiteralInterface|Stringable|string|float|int $then,
        QueryInterface|QueryLiteralInterface|Stringable|string|float|int $else
    ): QueryStructInterface
    {
        return new IfStruct($expression, $then, $else);
    }

    /**
     * Returns a `in($values)` struct.
     *
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $values
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see InStruct
     */
    public static function in(ArrayableInterface|array $values): QueryStructInterface
    {
        return new InStruct($values);
    }

    /**
     * Returns a `is not null` struct.
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see LiteralStruct
     */
    public static function isNotNull(): QueryStructInterface
    {
        return new LiteralStruct('is not null');
    }

    /**
     * Returns a new `is null` struct.
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see LiteralStruct
     */
    public static function isNull(): QueryStructInterface
    {
        return new LiteralStruct('is null');
    }

    /**
     * Returns a new `match($fields) against ($expression)` struct.
     *
     * @param QueryLiteralInterface|QueryStructInterface|Stringable|ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|string|float|int|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $fields
     * @param QueryLiteralInterface|QueryStructInterface|Stringable|string|float|int $expression
     * @param bool $booleanMode
     * @param bool $queryExpansion
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function matchAgainst(
        QueryLiteralInterface|QueryStructInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        QueryLiteralInterface|QueryStructInterface|Stringable|string|float|int $expression,
        bool $booleanMode = false,
        bool $queryExpansion = false
    ): QueryStructInterface
    {
        return new MatchAgainstStruct($fields, $expression, $booleanMode, $queryExpansion);
    }

    /**
     * Returns a `not $struct` struct.
     *
     * @param QueryStructInterface $struct
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see NotStruct
     */
    public static function not(QueryStructInterface $struct): QueryStructInterface
    {
        return new NotStruct($struct);
    }

    /**
     * Returns a `($subQuery)` struct.
     *
     * @param QueryInterface $query
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see SubQueryStruct
     */
    public static function subQuery(QueryInterface $query): QueryStructInterface
    {
        return new SubQueryStruct($query);
    }

    /**
     * Returns a `@$name:= ($subQuery)` struct.
     *
     * @param string $name
     * @param SubQueryStruct $subQuery
     *
     * @return QueryStructInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see VariableStruct
     * @see SubQueryStruct
     */
    public static function variable(string $name, SubQueryStruct $subQuery): QueryStructInterface
    {
        return new VariableStruct($name, $subQuery);
    }

}
