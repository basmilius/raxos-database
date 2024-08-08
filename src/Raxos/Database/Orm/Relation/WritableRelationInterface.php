<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Orm\Model;

/**
 * Interface WritableRelationInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
interface WritableRelationInterface
{

    /**
     * Writes to the relation and updates the appropriate column.
     *
     * @param Model $instance
     * @param ColumnDefinition $column
     * @param mixed $newValue
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function write(Model $instance, ColumnDefinition $column, mixed $newValue): void;

}
