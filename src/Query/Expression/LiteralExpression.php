<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryExpressionInterface};
use Stringable;

/**
 * Class LiteralStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class LiteralExpression implements QueryExpressionInterface
{

    /**
     * LiteralStruct constructor.
     *
     * @param Stringable|string|int|float|bool|null $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public Stringable|string|int|float|bool|null $value
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw((string)$this->value);
    }

}
