<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryExpressionInterface};

/**
 * Class GroupConcatStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class GroupConcatExpression implements QueryExpressionInterface
{

    /**
     * GroupConcatStruct constructor.
     *
     * @param QueryLiteralInterface|string $expression
     * @param bool $distinct
     * @param QueryLiteralInterface|string|null $orderBy
     * @param string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryLiteralInterface|string $expression,
        public bool $distinct = false,
        public QueryLiteralInterface|string|null $orderBy = null,
        public ?string $separator = null,
        public ?int $limit = null,
        public ?int $offset = null
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $distinct = $this->distinct ? 'distinct ' : '';
        $orderBy = $this->orderBy ? " order by {$this->orderBy}" : '';
        $separator = $this->separator ? " separator '{$this->separator}'" : '';
        $limit = $this->limit ? " limit {$this->limit}" : '';
        $offset = $this->offset ? " offset {$this->offset}" : '';

        $query->raw("group_concat({$distinct}{$this->expression}{$orderBy}{$separator}{$limit}{$offset})");
    }

}
