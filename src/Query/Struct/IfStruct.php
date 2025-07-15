<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Query\StructHelper;
use Stringable;

/**
 * Class IfStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.6.1
 */
final readonly class IfStruct implements QueryStructInterface
{

    /**
     * IfStruct constructor.
     *
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int $expression
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int $then
     * @param QueryInterface|QueryLiteralInterface|Stringable|string|float|int $else
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public function __construct(
        public QueryInterface|QueryLiteralInterface|Stringable|string|float|int $expression,
        public QueryInterface|QueryLiteralInterface|Stringable|string|float|int $then,
        public QueryInterface|QueryLiteralInterface|Stringable|string|float|int $else
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $expression = StructHelper::compileValue($connection, $this->expression);
        $then = StructHelper::compileValue($connection, $this->then);
        $else = StructHelper::compileValue($connection, $this->else);

        $query->raw("if({$expression}, {$then}, {$else})");
    }

}
