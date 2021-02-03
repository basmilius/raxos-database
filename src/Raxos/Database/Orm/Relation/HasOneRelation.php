<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Query;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use function array_column;
use function array_filter;
use function array_unique;

/**
 * Class HasOneRelation
 *
 * @template TModel of \Raxos\Database\Orm\Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
class HasOneRelation extends Relation
{

    /**
     * HasOneRelation constructor.
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
        protected string $key,
        protected string $referenceKey
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName);
    }

    /**
     * Gets the column.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getKey(): string
    {
        return $this->key;
    }

    /**
     * Gets the referenced column.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceKey(): string
    {
        return $this->referenceKey;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(Model $model): Model
    {
        $query = $this->getQuery($model);

        if ($this->connection->getCache()->has($this->getReferenceModel(), $model->{$this->key})) {
            return $this->connection->getCache()->get($this->getReferenceModel(), $model->{$this->key});
        }

        return $query->single();
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
            ->where($this->referenceKey, $model->{$this->key});
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

        $values = array_column($models, $this->key);
        $values = array_unique($values);
        $values = array_filter($values, fn($value) => !$this->connection->getCache()->has($this->getReferenceModel(), $value));

        if (empty($values)) {
            return;
        }

        $referenceModel::query(false)
            ->select($referenceModel::column('*'))
            ->from($referenceModel::getTable())
            ->where($this->referenceKey, ComparatorAwareLiteral::in($values))
            ->array();
    }

}
