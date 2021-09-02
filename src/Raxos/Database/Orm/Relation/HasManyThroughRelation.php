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
     * @param string $key
     * @param string $referenceKey
     * @param class-string<TModel> $throughModel
     * @param string $throughKey
     * @param string $referenceThroughKey
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
        protected string $throughModel,
        protected string $throughKey,
        protected string $referenceThroughKey
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName, $key, $referenceKey);
    }

    /**
     * Gets the through key.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getThroughKey(): string
    {
        return $this->throughKey;
    }

    /**
     * Gets the through model.
     *
     * @return class-string<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getThroughModel(): string
    {
        return $this->throughModel;
    }

    /**
     * Gets the reference through key.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceThroughKey(): string
    {
        return $this->referenceThroughKey;
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

        return $referenceModel::select()
            ->join($throughModel::getTable(), fn(Query $q) => $q
                ->on($throughModel::column($this->referenceThroughKey), Literal::with($referenceModel::column($this->referenceKey))))
            ->where($throughModel::column($this->throughKey), $model->{$this->key});
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
        $referenceModel = $this->referenceModel;

        /** @var Model $throughModel */
        $throughModel = $this->throughModel;

        return $referenceModel::select()
            ->join($throughModel::getTable(), fn(Query $q) => $q
                ->on($throughModel::column($this->referenceThroughKey), Literal::with($referenceModel::column($this->referenceKey))))
            ->where($throughModel::column($this->throughKey), $modelClass::column($this->key, literal: true));
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
