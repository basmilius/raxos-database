<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Stringable;

/**
 * Class ComparatorAwareLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
class ComparatorAwareLiteral extends Literal
{

    /**
     * Returns a `between $from and $to` literal.
     *
     * @param Stringable|Literal|string|float|int $from
     * @param Stringable|Literal|string|float|int $to
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function between(
        Stringable|Literal|string|float|int $from,
        Stringable|Literal|string|float|int $to
    ): self
    {
        return new BetweenComparatorAwareLiteral($from, $to);
    }

    /**
     * Returns a `in ($options)` literal.
     *
     * @param array $options
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function in(array $options): self
    {
        return new InComparatorAwareLiteral($options);
    }

    /**
     * Returns a `not in ($options)` literal.
     *
     * @param array $options
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function notIn(array $options): self
    {
        return new NotInComparatorAwareLiteral($options);
    }

    /**
     * Returns a `is not null` literal.
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function isNotNull(): self
    {
        return new self('is not null');
    }

    /**
     * Returns a `is null` literal.
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function isNull(): self
    {
        return new self('is null');
    }

}
