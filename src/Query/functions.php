<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Closure;
use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\Query\{QueryInterface, QueryLiteralInterface};
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use Stringable;

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
 * Returns a reusable sub-query partial. The `$query` closure is invoked lazily
 * when the partial is compiled, with the connection of the host query, so the
 * partial can be constructed without a live connection and used anywhere an
 * expression is accepted (e.g., Expr::exists()).
 *
 * @param Closure(ConnectionInterface):QueryInterface $query
 *
 * @return Partial
 * @author Bas Milius <bas@mili.us>
 * @since 3.0.0
 * @see Partial
 */
function partial(Closure $query): Partial
{
    return new Partial($query);
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
