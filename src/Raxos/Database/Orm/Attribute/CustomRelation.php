<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Defenition\FieldDefinition;
use Raxos\Database\Orm\Relation\Relation;

/**
 * Class CustomRelation
 *
 * @author Bas Milius <bas@glybe.nl>
 * @package Raxos\Database\Orm\Attribute
 * @since 2.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class CustomRelation extends RelationAttribute
{

    /**
     * CustomRelation constructor.
     *
     * @param string $relationClass
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@glybe.nl>
     * @since 2.0.0
     */
    public function __construct(
        private string $relationClass,
        bool $eagerLoad = false
    )
    {
        parent::__construct($eagerLoad);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 2.0.0
     */
    public final function create(Connection $connection, string $modelClass, FieldDefinition $field): Relation
    {
        return new $this->relationClass($connection, $this->eagerLoad, $field);
    }

}
