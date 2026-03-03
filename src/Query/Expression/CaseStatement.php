<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\GrammarInterface;
use Raxos\Contract\Database\Query\QueryExpressionInterface;
use Raxos\Contract\Database\Query\QueryInterface;

/**
 * Class CaseStatement
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.1.0
 */
final readonly class CaseStatement implements QueryExpressionInterface
{

    /**
     * CaseStatement constructor.
     *
     * @param QueryExpressionInterface[] $exprs
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function __construct(
        public array $exprs
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('case ');
        $query->compileMultiple($this->exprs, ' ');
        $query->raw('end');
    }

}
