<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Query\Struct\{BetweenComparatorAwareLiteral, ComparatorAwareLiteral, InComparatorAwareLiteral, Literal, NotInComparatorAwareLiteral};
use Stringable;

/**
 * Returns a `coalesce($a, $b)` literal.
 *
 * @param Literal|QueryInterface|string $a
 * @param Literal|QueryInterface|string $b
 *
 * @return Literal
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.16
 */
function coalesce(
    Literal|QueryInterface|string $a,
    Literal|QueryInterface|string $b
): Literal
{
    if ($a instanceof QueryInterface) {
        $a = "({$a})";
    }

    if ($b instanceof QueryInterface) {
        $b = "({$b})";
    }

    return new Literal("coalesce({$a}, {$b})");
}

/**
 * Returns a `$value` literal.
 *
 * @param Stringable|string|int|float|bool $value
 *
 * @return Literal
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see Literal
 * @see Literal::with()
 */
function literal(Stringable|string|int|float|bool $value): Literal
{
    return new Literal($value);
}

/**
 * Returns a `'$value'` literal.
 *
 * @param Stringable|string $value
 *
 * @return Literal
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see Literal
 * @see Literal::string()
 */
function stringLiteral(Stringable|string $value): Literal
{
    return Literal::string((string)$value);
}

/**
 * Returns a `between $from and $to` literal.
 *
 * @param Stringable|Literal|string|float|int $lower
 * @param Stringable|Literal|string|float|int $upper
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see BetweenComparatorAwareLiteral
 */
function between(
    Stringable|Literal|string|float|int $lower,
    Stringable|Literal|string|float|int $upper
): Literal
{
    return new BetweenComparatorAwareLiteral($lower, $upper);
}

/**
 * Returns a `in($options)` literal.
 *
 * @param array $options
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see InComparatorAwareLiteral
 */
function in(array $options): Literal
{
    return new InComparatorAwareLiteral($options);
}

/**
 * Returns a `not in($options)` literal.
 *
 * @param array $options
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see NotInComparatorAwareLiteral
 */
function notIn(array $options): Literal
{
    return new NotInComparatorAwareLiteral($options);
}

/**
 * Returns a `is not null` literal.
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see ComparatorAwareLiteral::isNotNull()
 */
function isNotNull(): Literal
{
    return ComparatorAwareLiteral::isNotNull();
}

/**
 * Returns a `is null` literal.
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see ComparatorAwareLiteral::isNull()
 */
function isNull(): Literal
{
    return ComparatorAwareLiteral::isNull();
}
