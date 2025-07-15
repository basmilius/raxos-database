<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryStructInterface};

/**
 * Class ExistsStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.5.0
 */
final readonly class ExistsStruct implements QueryStructInterface
{

    /**
     * ExistsStruct constructor.
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
        $query->raw("exists ");
        $this->struct->compile($query, $connection, $grammar);
    }

}
