<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Query\QueryBaseInterface;

/**
 * Class Value
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
abstract class Value
{

    /**
     * Gets the value. The query instance is provided for setting params when needed.
     *
     * @param QueryBaseInterface $query
     *
     * @return string|int|float
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function get(QueryBaseInterface $query): string|int|float;

}
