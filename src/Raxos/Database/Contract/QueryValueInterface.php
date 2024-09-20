<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

/**
 * Interface QueryValueInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.0.16
 */
interface QueryValueInterface
{

    /**
     * Returns the value. The query instance is provided for setting
     * params when needed.
     *
     * @param QueryInterface $query
     *
     * @return string|int|float
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function get(QueryInterface $query): string|int|float;

}
