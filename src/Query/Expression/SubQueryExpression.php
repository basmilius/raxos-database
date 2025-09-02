<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryExpressionInterface};

/**
 * Class SubQueryStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class SubQueryExpression implements QueryExpressionInterface
{

    /**
     * SubQueryStruct constructor.
     *
     * @param QueryInterface $query
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryInterface $query
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->parenthesis(fn() => $query->merge($this->query), patch: false);
    }

}
