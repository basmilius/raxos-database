<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use Raxos\Database\Error\QueryException;

/**
 * Interface AfterQueryExpressionInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.0.0
 */
interface AfterQueryExpressionInterface
{

    /**
     * Executes after an expression is added to the given query.
     *
     * @param QueryInterface $query
     *
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function after(QueryInterface $query): void;

}
