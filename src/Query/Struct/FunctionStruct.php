<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use BackedEnum;
use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryStructInterface, QueryValueInterface};
use Raxos\Database\Query\QueryHelper;
use Stringable;

/**
 * Class FunctionStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 2.0.0
 */
readonly class FunctionStruct implements QueryStructInterface
{

    /**
     * FunctionStruct constructor.
     *
     * @param string $name
     * @param array<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $name,
        public array $params = []
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw("{$this->name}(");

        foreach ($this->params as $index => $param) {
            if ($index > 0) {
                $query->raw(', ');
            }

            QueryHelper::value($query, $param);
        }

        $query->raw(')');
    }

}
