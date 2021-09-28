<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Literal;
use WeakMap;
use function array_map;
use function explode;
use function in_array;

/**
 * Class HasLinkedManyRelation
 *
 * @template TModel of \Raxos\Database\Orm\Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
class HasLinkedManyRelation extends HasManyRelation
{

    public static WeakMap $linkingKeys;

    /**
     * HasLinkedManyRelation constructor.
     *
     * @param Connection $connection
     * @param class-string<TModel> $referenceModel
     * @param bool $eagerLoad
     * @param string $fieldName
     * @param string $key
     * @param string $referenceKey
     * @param string $linkingKey
     * @param string $linkingReferenceKey
     * @param string $linkingTable
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        Connection $connection,
        string $referenceModel,
        bool $eagerLoad,
        string $fieldName,
        string $key,
        string $referenceKey,
        protected string $linkingKey,
        protected string $linkingReferenceKey,
        protected string $linkingTable
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName, $key, $referenceKey);
    }

    /**
     * Gets the linking key.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLinkingKey(): string
    {
        return $this->linkingKey;
    }

    /**
     * Gets the linking reference key.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLinkingReferenceKey(): string
    {
        return $this->linkingReferenceKey;
    }

    /**
     * Gets the linking table.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLinkingTable(): string
    {
        return $this->linkingTable;
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

        return $referenceModel::select()
            ->join($this->linkingTable, fn(Query $q) => $q
                ->on("{$this->linkingTable}.{$this->linkingReferenceKey}", Literal::with($referenceModel::column($this->referenceKey))))
            ->where("{$this->linkingTable}.{$this->linkingKey}", $model->{$this->key});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getRaw(string $modelClass, bool $isPrepared): Query
    {
        /** @var Model $modelClass */
        /** @var Model $referenceModel */
        $referenceModel = $this->getReferenceModel();

        return $referenceModel::select()
            ->join($this->linkingTable, fn(Query $q) => $q
                ->on("{$this->linkingTable}.{$this->linkingReferenceKey}", Literal::with($referenceModel::column($this->referenceKey))))
            ->where("{$this->linkingTable}.{$this->linkingKey}", $modelClass::column($this->key, literal: true));
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

        $values = array_filter($models, fn(Model $model) => !isset($this->results[$model->getModelMaster()]));
        $values = array_column($values, $this->key);
        $values = array_unique($values);

        if (empty($values)) {
            return;
        }

        $results = $referenceModel::select(['__linking_key' => "group_concat({$this->linkingTable}.{$this->linkingKey})"])
            ->leftJoin($this->linkingTable, fn(Query $q) => $q
                ->on("{$this->linkingTable}.{$this->linkingReferenceKey}", Literal::with($referenceModel::column($this->referenceKey))))
            ->where("{$this->linkingTable}.{$this->linkingKey}", ComparatorAwareLiteral::in($values))
            ->groupBy($referenceModel::column($this->referenceKey))
            ->array();

        $entries = [];

        foreach ($models as $model) {
            if (isset($this->results[$model->getModelMaster()])) {
                $references = $this->results[$model->getModelMaster()]->toArray();
            } else {
                $references = [];
            }

            foreach ($results as $result) {
                $entry = $entries[$result->{$this->referenceKey}] ??= $result;

                if (!in_array($model->{$this->key}, self::$linkingKeys[$result] ?? [])) {
                    continue;
                }

                $references[] = $entry;
            }

            $this->results[$model->getModelMaster()] = new ModelArrayList($references);
        }
    }

}
