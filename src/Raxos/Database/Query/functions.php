<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Query\Struct\{BetweenComparatorAwareLiteral, ComparatorAwareLiteral, InComparatorAwareLiteral, Literal, NotInComparatorAwareLiteral};
use Stringable;

/**
 * Returns a `$value` literal.
 *
 * @param string|int|float|bool $value
 *
 * @return Literal
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see Literal
 * @see Literal::with()
 */
function literal(string|int|float|bool $value): Literal
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
 * @param Literal|string|float|int $from
 * @param Literal|string|float|int $to
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see BetweenComparatorAwareLiteral
 */
function between(Literal|string|float|int $from, Literal|string|float|int $to): ComparatorAwareLiteral
{
    return new BetweenComparatorAwareLiteral($from, $to);
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
function in(array $options): ComparatorAwareLiteral
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
function notIn(array $options): ComparatorAwareLiteral
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
function isNotNull(): ComparatorAwareLiteral
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
function isNull(): ComparatorAwareLiteral
{
    return ComparatorAwareLiteral::isNull();
}
