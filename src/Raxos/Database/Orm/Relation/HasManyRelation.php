<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use WeakMap;
use function array_column;
use function array_filter;
use function array_map;
use function array_unique;

/**
 * Class HasManyRelation
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
class HasManyRelation extends Relation
{

    private WeakMap $results;

    /**
     * HasManyRelation constructor.
     *
     * @template M of \Raxos\Database\Orm\Model
     *
     * @param Connection $connection
     * @param string $referenceModel
     * @param bool $eagerLoad
     * @param string $fieldName
     * @param string $column
     * @param string $referenceColumn
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        Connection $connection,
        string $referenceModel,
        bool $eagerLoad,
        string $fieldName,
        protected string $column,
        protected string $referenceColumn
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName);

        $this->results = new WeakMap();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(Model $model): array
    {
        if (isset($this->results[$model])) {
            return $this->results[$model];
        }

        $results = $this
            ->getQuery($model)
            ->array();

        $results = array_map(function (Model $model): Model {
            if ($this->connection->getCache()->has($model::class, $model->getPrimaryKeyValues())) {
                return $this->connection->getCache()->get($model::class, $model->getPrimaryKeyValues());
            }

            return $model;
        }, $results);

        return $results;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getQuery(Model $model): Query
    {
        /** @var Model $referenceModel */
        $referenceModel = $this->getReferenceModel();

        return $referenceModel::query(false)
            ->select($referenceModel::column('*'))
            ->from($referenceModel::getTable())
            ->where($this->referenceColumn, $model->{$this->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(array $models): void
    {
        /** @var Model $referenceModel */
        $referenceModel = $this->getReferenceModel();

        $values = array_filter($models, fn(Model $model) => !isset($this->results[$model]));
        $values = array_column($values, $this->column);
        $values = array_unique($values);

        if (empty($values)) {
            return;
        }

        $results = $referenceModel::query(false)
            ->select($referenceModel::column('*'))
            ->from($referenceModel::getTable())
            ->where($this->referenceColumn, ComparatorAwareLiteral::in($values))
            ->array();

        foreach ($models as $model) {
            $this->results[$model] = [];

            foreach ($results as $result) {
                if ($result->{$this->referenceColumn} !== $model->{$this->column}) {
                    continue;
                }

                $this->results[$model][] = $result;
            }
        }
    }

}
