<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryValueInterface};

/**
 * Class When
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.1.0
 */
final readonly class When implements QueryExpressionInterface
{

    /**
     * When constructor.
     *
     * @param QueryExpressionInterface $when
     * @param QueryExpressionInterface|QueryValueInterface $then
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function __construct(
        public QueryExpressionInterface $when,
        public QueryExpressionInterface|QueryValueInterface $then
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('when ');
        $query->compile($this->when);
        $query->raw(' then ');
        $query->compile($this->then);
    }

}
