<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use Raxos\Database\Error\QueryException;

/**
 * Interface BeforeQueryExpressionInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.0.0
 */
interface BeforeQueryExpressionInterface
{

    /**
     * Executes before an expression is added to the given query.
     *
     * @param QueryInterface $query
     *
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function before(QueryInterface $query): void;

}
