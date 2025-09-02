<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface, QueryLiteralInterface};
use Stringable;

/**
 * Class CoalesceStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class CoalesceExpression implements QueryExpressionInterface
{

    /**
     * CoalesceStruct constructor.
     *
     * @param iterable<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $values
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public iterable $values
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('coalesce(');
        $query->compileMultiple($this->values);
        $query->raw(')');
    }

}
