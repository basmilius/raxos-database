<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Query\QueryBase;
use function is_string;

/**
 * Class BetweenComparatorAwareLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
class BetweenComparatorAwareLiteral extends ComparatorAwareLiteral
{

    /**
     * BetweenComparatorAwareLiteral constructor.
     *
     * @param string|float|int $from
     * @param string|float|int $to
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(private string|float|int $from, private string|float|int $to)
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(QueryBase $query): string|int|float
    {
        if (is_string($this->from)) {
            $this->from = $query->getConnection()->quote($this->from);
        }

        if (is_string($this->to)) {
            $this->to = $query->getConnection()->quote($this->to);
        }

        return "between {$this->from} and {$this->to}";
    }

}
