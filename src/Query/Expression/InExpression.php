<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryExpressionInterface};
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;

/**
 * Class InStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class InExpression implements QueryExpressionInterface
{

    /**
     * InStruct constructor.
     *
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $values
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public ArrayableInterface|array $values
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('in(');
        $query->compileMultiple($this->values);
        $query->raw(')');
    }

}
