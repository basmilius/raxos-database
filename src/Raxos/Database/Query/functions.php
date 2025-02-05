<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\QueryLiteralInterface;
use Raxos\Database\Query\Literal\Literal;
use Stringable;

/**
 * Returns a `$value` literal.
 *
 * @param Stringable|string|int|float|bool $value
 *
 * @return QueryLiteralInterface
 * @author Bas Milius <bas@mili.us>
 * @since 1.5.0
 * @see Literal::of()
 */
function literal(Stringable|string|int|float|bool $value): QueryLiteralInterface
{
    return Literal::of($value);
}

/**
 * Returns a `'$value'` literal.
 *
 * @param Stringable|string $value
 *
 * @return QueryLiteralInterface
 * @author Bas Milius <bas@mili.us>
 * @since 1.5.0
 * @see Literal::string()
 */
function stringLiteral(Stringable|string $value): QueryLiteralInterface
{
    return Literal::string((string)$value);
}
