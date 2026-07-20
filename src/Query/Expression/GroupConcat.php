<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryValueInterface};
use Stringable;

/**
 * Class GroupConcat
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class GroupConcat implements QueryExpressionInterface
{

    /**
     * GroupConcat constructor.
     *
     * @param BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr
     * @param bool $distinct
     * @param QueryValueInterface|string|null $orderBy
     * @param QueryValueInterface|string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
        public bool $distinct = false,
        public QueryValueInterface|string|null $orderBy = null,
        public QueryValueInterface|string|null $separator = null,
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
        $query->raw('group_concat(');
        $this->distinct && $query->raw('distinct ');
        $query->compile($this->expr);
        $this->orderBy && $query->raw(' order by ')->compile($this->orderBy);
        $this->separator !== null && $query->raw(' separator ')->compile($this->separator);
        $this->limit && $query->raw(" limit {$this->limit}");
        $this->offset && $query->raw(" offset {$this->offset}");
        $query->raw(')');
    }

}
