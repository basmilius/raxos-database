<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryExpressionInterface, QueryInterface, QueryLiteralInterface, QueryValueInterface};
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
     * @param QueryLiteralInterface|string|null $orderBy
     * @param string|null $separator
     * @param int|null $limit
     * @param int|null $offset
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public BackedEnum|QueryValueInterface|Stringable|string|int|float|bool $expr,
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
        $query->raw('group_concat(');
        $this->distinct && $query->raw('distinct ');
        $query->compile($this->expr);
        $this->orderBy && $query->raw(" order by {$this->orderBy}");
        $this->separator && $query->raw(" separator {$this->separator}");
        $this->limit && $query->raw(" limit {$this->limit}");
        $this->offset && $query->raw(" offset {$this->offset}");
        $query->raw(')');
    }

}
