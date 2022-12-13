<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Query\QueryBaseInterface;

/**
 * Interface BeforeExpressionInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
interface BeforeExpressionInterface
{

    /**
     * Executes before an expression is added to the given query.
     *
     * @param QueryBaseInterface $query
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function before(QueryBaseInterface $query): void;

}
