<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, QueryException};
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Query\Struct\Select;

/**
 * Interface QueryableInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.0.17
 */
interface QueryableInterface
{

    /**
     * Gets the columns that the model uses in addition to the record itself.
     *
     * @param Select $select
     *
     * @return Select
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableColumns(Select $select): Select;

    /**
     * Gets the joins that model queries use.
     *
     * @param QueryInterface<static> $query
     *
     * @return QueryInterface<static>
     * @throws ConnectionException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableJoins(QueryInterface $query): QueryInterface;

}
