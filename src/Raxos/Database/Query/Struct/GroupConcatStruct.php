<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};

/**
 * Class GroupConcatStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.5.0
 */
final readonly class GroupConcatStruct implements QueryStructInterface
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
     * @since 1.5.0
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
     * @since 1.5.0
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
