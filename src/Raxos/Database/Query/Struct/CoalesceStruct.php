<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Query\StructHelper;
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;
use function array_map;

/**
 * Class CoalesceStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.5.0
 */
final readonly class CoalesceStruct implements QueryStructInterface
{

    /**
     * CoalesceStruct constructor.
     *
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $values
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public ArrayableInterface|array $values
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $values = $this->values;

        if ($values instanceof ArrayableInterface) {
            $values = $values->toArray();
        }

        $values = array_map(fn(mixed $value) => StructHelper::compileValue($connection, $value), $values);
        $values = implode(', ', $values);

        $query->raw("coalesce({$values})");
    }

}
