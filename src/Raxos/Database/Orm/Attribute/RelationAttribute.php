<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\Definition\FieldDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\Relation;

/**
 * Class RelationAttribute
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
abstract readonly class RelationAttribute
{

    /**
     * RelationAttribute constructor.
     *
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(public bool $eagerLoad = false)
    {
    }

    /**
     * Creates the relation instance.
     *
     * @param Connection $connection
     * @param class-string<Model> $modelClass
     * @param FieldDefinition $field
     *
     * @return Relation
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function create(Connection $connection, string $modelClass, FieldDefinition $field): Relation;

}
