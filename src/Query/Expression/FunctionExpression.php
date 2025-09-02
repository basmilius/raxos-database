<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryExpressionInterface, QueryValueInterface};
use Stringable;

/**
 * Class FunctionStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
readonly class FunctionExpression implements QueryExpressionInterface
{

    /**
     * FunctionStruct constructor.
     *
     * @param string $name
     * @param iterable<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $name,
        public iterable $params = []
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw("{$this->name}(");
        $query->compileMultiple($this->params);
        $query->raw(')');
    }

}
