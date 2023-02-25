<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Query\QueryInterface;
use Raxos\Foundation\Collection\CollectionException;
use WeakMap;
use function array_column;
use function array_filter;
use function array_unique;
use function Raxos\Database\Query\{in, literal, stringLiteral};
use function is_int;

/**
 * Class HasManyRelation
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
class HasManyRelation extends Relation
{

    protected WeakMap $results;

    /**
     * HasManyRelation constructor.
     *
     * @template M of Model
     *
     * @param Connection $connection
     * @param class-string<TModel> $referenceModel
     * @param bool $eagerLoad
     * @param string $fieldName
     * @param string $key
     * @param string $referenceKey
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        Connection $connection,
        string $referenceModel,
        bool $eagerLoad,
        string $fieldName,
        public readonly string $key,
        public readonly string $referenceKey
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName);

        $this->results = new WeakMap();
    }

    /**
     * {@inheritdoc}
     * @throws CollectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(Model $model): ModelArrayList
    {
        return $this->results[$model->getModelMaster()] ??= $this
            ->getQuery($model)
            ->arrayList()
            ->map(function (Model $referenceModel): Model {
                $cache = $this->connection->cache;
                $pk = $referenceModel->getPrimaryKeyValues();

                if ($cache->has($referenceModel::class, $pk)) {
                    return $cache->get($referenceModel::class, $pk);
                }

                return $referenceModel;
            });
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getQuery(Model $model): QueryInterface
    {
        /** @var Model $referenceModel */
        $referenceModel = $this->referenceModel;

        return $referenceModel::select()
            ->where($this->referenceKey, $model->{$this->key});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getRaw(string $modelClass, bool $isPrepared): QueryInterface
    {
        /** @var Model $modelClass */
        /** @var Model $referenceModel */
        $referenceModel = $this->referenceModel;

        return $referenceModel::select(isPrepared: $isPrepared)
            ->where($this->referenceKey, $modelClass::column($this->key));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(array $models): void
    {
        /** @var Model $referenceModel */
        $referenceModel = $this->referenceModel;

        $values = array_filter($models, fn(Model $model) => !isset($this->results[$model->getModelMaster()]));
        $values = array_column($values, $this->key);
        $values = array_unique($values);
        $values = array_filter($values, fn($value) => !$this->connection->cache->has($this->referenceModel, $value));
        $values = array_values($values);

        if (empty($values)) {
            return;
        }

        if (!isset($values[1])) {
            $results = $referenceModel::select()
                ->where($this->referenceKey, is_int($values[0]) ? literal($values[0]) : stringLiteral($values[0]))
                ->array();
        } else {
            $results = $referenceModel::select()
                ->where($this->referenceKey, in($values))
                ->array();
        }

        foreach ($models as $model) {
            $references = [];

            foreach ($results as $result) {
                if ($result->{$this->referenceKey} !== $model->{$this->key}) {
                    continue;
                }

                $references[] = $result;
            }

            $this->results[$model->getModelMaster()] = new ModelArrayList($references);
        }
    }

}
