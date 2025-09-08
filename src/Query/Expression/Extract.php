<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface, QueryValueInterface};
use Raxos\Database\Query\DateTimeUnit;
use Stringable;

/**
 * Class Extract
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class Extract implements QueryExpressionInterface
{

    /**
     * Extract constructor.
     *
     * @param DateTimeUnit $unit
     * @param QueryValueInterface|Stringable|string $date
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public DateTimeUnit $unit,
        public QueryValueInterface|Stringable|string $date
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('extract(');
        $query->raw("{$this->unit->value} from ");
        $query->compile($this->date);
        $query->raw(')');
    }

}
