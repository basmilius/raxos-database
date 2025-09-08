<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface};

/**
 * Class AggregateFunc
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class AggregateFunc implements QueryExpressionInterface
{

    /**
     * AggregateFunc constructor.
     *
     * @param string $name
     * @param iterable $params
     * @param bool $distinct
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $name,
        public iterable $params,
        public bool $distinct = false
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw("{$this->name}(");
        $this->distinct && $query->raw('distinct ');
        $query->compileMultiple($this->params);
        $query->raw(')');
    }

}
