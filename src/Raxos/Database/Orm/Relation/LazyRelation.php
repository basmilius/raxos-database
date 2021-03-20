<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Attribute\RelationAttribute;
use Raxos\Database\Orm\Defenition\FieldDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Query;

/**
 * Class LazyRelation
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
final class LazyRelation extends Relation
{

    private ?Relation $relation = null;

    /**
     * LazyRelation constructor.
     *
     * @param RelationAttribute $attribute
     * @param string $modelClass
     * @param FieldDefinition $field
     * @param Connection $connection
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        private RelationAttribute $attribute,
        private string $modelClass,
        private FieldDefinition $field,
        Connection $connection
    )
    {
        parent::__construct($connection, '', $attribute->isEagerLoadEnabled(), $field->name);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(Model $model): Model|ModelArrayList|null
    {
        $this->relation ??= $this->attribute->create($this->connection, $this->modelClass, $this->field);

        return $this->relation->get($model);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getQuery(Model $model): Query
    {
        $this->relation ??= $this->attribute->create($this->connection, $this->modelClass, $this->field);

        return $this->relation->getQuery($model);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(array $models): void
    {
        $this->relation ??= $this->attribute->create($this->connection, $this->modelClass, $this->field);
        $this->relation->eagerLoad($models);
    }

}
