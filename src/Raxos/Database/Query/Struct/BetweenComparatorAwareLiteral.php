<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Query\QueryBase;
use Stringable;
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
     * @param Stringable|Literal|string|float|int $from
     * @param Stringable|Literal|string|float|int $to
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        private Stringable|Literal|string|float|int $from,
        private Stringable|Literal|string|float|int $to
    )
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
        if (!($this->from instanceof Literal) && (is_string($this->from) || $this->from instanceof Stringable)) {
            $this->from = $query->connection->quote((string)$this->from);
        }

        if (!($this->to instanceof Literal) && (is_string($this->to) || $this->to instanceof Stringable)) {
            $this->to = $query->connection->quote((string)$this->to);
        }

        return "between {$this->from} and {$this->to}";
    }

}
