<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryStructInterface};

/**
 * Class NotStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.5.0
 */
final readonly class NotStruct implements QueryStructInterface
{

    /**
     * NotStruct constructor.
     *
     * @param QueryStructInterface $struct
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public QueryStructInterface $struct
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw("not ");
        $this->struct->compile($query, $connection, $grammar);
    }

}
