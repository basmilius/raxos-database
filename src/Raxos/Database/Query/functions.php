<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Query\Struct\BetweenComparatorAwareLiteral;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\InComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Literal;
use Raxos\Database\Query\Struct\NotInComparatorAwareLiteral;
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
    return Literal::with($value);
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
 * @see ComparatorAwareLiteral::between()
 * @see BetweenComparatorAwareLiteral
 */
function between(Literal|string|float|int $from, Literal|string|float|int $to): ComparatorAwareLiteral
{
    return ComparatorAwareLiteral::between($from, $to);
}

/**
 * Returns a `in($options)` literal.
 *
 * @param array $options
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see ComparatorAwareLiteral::in()
 * @see InComparatorAwareLiteral
 */
function in(array $options): ComparatorAwareLiteral
{
    return ComparatorAwareLiteral::in($options);
}

/**
 * Returns a `not in($options)` literal.
 *
 * @param array $options
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see ComparatorAwareLiteral::notIn()
 * @see NotInComparatorAwareLiteral
 */
function notIn(array $options): ComparatorAwareLiteral
{
    return ComparatorAwareLiteral::notIn($options);
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
