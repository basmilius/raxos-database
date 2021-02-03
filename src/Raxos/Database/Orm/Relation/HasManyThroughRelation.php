<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\Struct\Literal;

/**
 * Class HasManyThroughRelation
 *
 * @template TModel of \Raxos\Database\Orm\Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
class HasManyThroughRelation extends HasManyRelation
{

    /**
     * HasManyThroughRelation constructor.
     *
     * @param Connection $connection
     * @param class-string<TModel> $referenceModel
     * @param bool $eagerLoad
     * @param string $fieldName
     * @param string $column
     * @param string $referenceColumn
     * @param class-string<TModel> $throughModel
     * @param string $throughColumn
     * @param string $referenceThroughColumn
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        Connection $connection,
        string $referenceModel,
        bool $eagerLoad,
        string $fieldName,
        string $column,
        string $referenceColumn,
        protected string $throughModel,
        protected string $throughColumn,
        protected string $referenceThroughColumn
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName, $column, $referenceColumn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getQuery(Model $model): Query
    {
        /** @var Model $referenceModel */
        $referenceModel = $this->referenceModel;

        /** @var Model $throughModel */
        $throughModel = $this->throughModel;

        return $referenceModel::query(false)
            ->select($referenceModel::column('*'))
            ->from($referenceModel::getTable())
            ->join($throughModel::getTable(), fn(Query $q) => $q
                ->on($throughModel::column($this->referenceThroughColumn), Literal::with($referenceModel::column($this->referenceColumn))))
            ->where($throughModel::column($this->throughColumn), $model->{$this->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(array $models): void
    {
        // todo(Bas): Implement eager loading for this relation.
    }

}
