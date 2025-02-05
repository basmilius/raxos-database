<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\{QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Query\Struct\{BetweenStruct, CoalesceStruct, ExistsStruct, GroupConcatStruct, InStruct, LiteralStruct, NotStruct, SubQueryStruct, VariableStruct};
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
