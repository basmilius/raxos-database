<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Contract\Database\Query\QueryLiteralInterface;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use Stringable;

const expr = new Expr();

/**
 * Returns a grammar-less `$table`.`$column` reference. Escaped lazily when the
 * expression is compiled, so it can be used inside other expressions and joins
 * without a connection.
 *
 * @param string $column
 * @param string|null $table
 * @param string|null $schema
 *
 * @return ColumnRef
 * @author Bas Milius <bas@mili.us>
 * @since 3.0.0
 * @see ColumnRef
 */
function column(string $column, ?string $table = null, ?string $schema = null): ColumnRef
{
    return new ColumnRef($column, $table, $schema);
}

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
