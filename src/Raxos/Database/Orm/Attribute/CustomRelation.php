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
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CustomRelation extends RelationAttribute
{

    /**
     * CustomRelation constructor.
     *
     * @param string $relationClass
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        protected readonly string $relationClass,
        bool $eagerLoad = false
    )
    {
        parent::__construct($eagerLoad);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function create(Connection $connection, string $modelClass, FieldDefinition $field): Relation
    {
        return new $this->relationClass($connection, $this->eagerLoad, $field);
    }

    /**
     * Restores the state of the class from exported data.
     *
     * @param array $state
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function __set_state(array $state): self
    {
        return new self(
            $state['relationClass'],
            $state['eagerLoad']
        );
    }

}
