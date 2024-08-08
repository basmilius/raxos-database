<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use JsonSerializable;
use Raxos\Database\Query\QueryBaseInterface;
use Stringable;

/**
 * Interface ValueInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.16
 */
interface ValueInterface extends JsonSerializable, Stringable
{

    /**
     * Returns the value. The query instance is provided for setting
     * params when needed.
     *
     * @param QueryBaseInterface $query
     *
     * @return string|int|float
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function get(QueryBaseInterface $query): string|int|float;

}
