<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Query\Struct\BetweenComparatorAwareLiteral;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\InComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Literal;
use function is_string;

/**
 * Returns a `$value` literal or a `'$value'` literal.
 *
 * @param string|int|float|bool $value
 *
 * @return Literal
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see Literal
 * @see Literal::string()
 * @see Literal::with()
 */
function literal(string|int|float|bool $value): Literal
{
    if (is_string($value)) {
        return Literal::string($value);
    }

    return Literal::with($value);
}

/**
 * Returns a `between $from and $to` literal.
 *
 * @param string|float|int $from
 * @param string|float|int $to
 *
 * @return ComparatorAwareLiteral
 * @author Bas Milius <bas@mili.us>
 * @since 1.0.0
 * @see ComparatorAwareLiteral::between()
 * @see BetweenComparatorAwareLiteral
 */
function between(string|float|int $from, string|float|int $to): ComparatorAwareLiteral
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
