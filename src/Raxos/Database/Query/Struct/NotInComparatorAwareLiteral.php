<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\QueryInterface;

/**
 * Class NotInComparatorAwareLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.2
 */
readonly class NotInComparatorAwareLiteral extends InComparatorAwareLiteral
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function get(QueryInterface $query): string
    {
        return 'not ' . parent::get($query);
    }

}
