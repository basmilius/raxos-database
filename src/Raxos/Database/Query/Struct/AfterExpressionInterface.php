<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Query\QueryBaseInterface;

/**
 * Interface AfterExpressionInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
interface AfterExpressionInterface
{

    /**
     * Executes after an expression is added to the given query.
     *
     * @param QueryBaseInterface $query
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function after(QueryBaseInterface $query): void;

}
