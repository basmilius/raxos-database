<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Closure;
use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface};

/**
 * Class Partial
 *
 * A reusable, connection-less sub-query fragment that behaves as a query
 * expression. Its query is built lazily at compile-time using the connection
 * of the host query, so a partial can be constructed without a live connection
 * and used anywhere an expression is accepted (e.g., Expr::exists()).
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 3.0.0
 */
final readonly class Partial implements QueryExpressionInterface
{

    /**
     * Partial constructor.
     *
     * @param Closure(ConnectionInterface):QueryInterface $query
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function __construct(
        private Closure $query
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->parenthesis(fn() => $query->merge(($this->query)($connection)), patch: false);
    }

}
