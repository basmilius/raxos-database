<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Collection\ArrayableInterface;
use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryLiteralInterface};
use Stringable;
use function is_iterable;

/**
 * Class MatchAgainst
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class MatchAgainst implements QueryExpressionInterface
{

    /**
     * MatchAgainst constructor.
     *
     * @param QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|string|float|int|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $fields
     * @param QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expression
     * @param bool $booleanMode
     * @param bool $queryExpansion
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryLiteralInterface|QueryExpressionInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        public QueryLiteralInterface|QueryExpressionInterface|Stringable|string|float|int $expression,
        public bool $booleanMode = false,
        public bool $queryExpansion = false
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $fields = $this->fields;

        if (!is_iterable($fields)) {
            $fields = [$fields];
        }

        $query->raw('match(');
        $query->compileMultiple($fields);
        $query->raw(') against (');
        $query->compile($this->expression);

        if ($this->booleanMode) {
            $query->raw('in boolean mode');
        }

        if ($this->queryExpansion) {
            $query->raw('with query expansion');
        }

        $query->raw(')');
    }

}
