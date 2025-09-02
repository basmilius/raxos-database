<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use Raxos\Database\Error\QueryException;

/**
 * Interface QueryExpressionInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 2.0.0
 */
interface QueryExpressionInterface extends QueryValueInterface
{

    /**
     * Returns the value. The query instance is provided for setting
     * params when needed.
     *
     * @param QueryInterface $query
     * @param ConnectionInterface $connection
     * @param GrammarInterface $grammar
     *
     * @return void
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void;

}
