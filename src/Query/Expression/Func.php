<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryExpressionInterface, QueryValueInterface};
use Stringable;

/**
 * Class Func
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class Func implements QueryExpressionInterface
{

    /**
     * Func constructor.
     *
     * @param string $name
     * @param iterable<BackedEnum|QueryValueInterface|Stringable|string|int|float|bool> $params
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
