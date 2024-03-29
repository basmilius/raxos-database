<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\QueryInterface;
use function array_column;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function intval;
use function is_int;
use function is_numeric;
use function Raxos\Database\Query\{in, literal, stringLiteral};

/**
 * Class HasOneRelation
 *
 * @template TModel of Model
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
        public readonly string $key,
        public readonly string $referenceKey
    )
    {
        parent::__construct($connection, $referenceModel, $eagerLoad, $fieldName);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(Model $model): ?Model
    {
        $pk = $model->{$this->key};

        if ($pk === null || (is_numeric($pk) && intval($pk) === 0)) {
            return null;
        }

        $referenceModel = $this->referenceModel;

        if ($model::cache()->has($referenceModel, $pk)) {
            return $model::cache()->get($referenceModel, $pk);
        }

        return $this
            ->getQuery($model)
            ->single();
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
            ->where($this->referenceKey, $modelClass::column($this->key, literal: true));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(array $models): void
    {
        /** @var Model|string $referenceModel */
        $referenceModel = $this->referenceModel;

        $values = array_column($models, $this->key);
        $values = array_unique($values);
        $values = array_filter($values, fn($value) => $value !== null && $value !== 0 && !$referenceModel::cache()->has($referenceModel, $value));
        $values = array_values($values);

        if (empty($values)) {
            return;
        }

        $values = array_map(fn(mixed $value) => is_int($value) ? literal($value) : stringLiteral($value), $values);

        if (!isset($values[1])) {
            $referenceModel::select()
                ->where($this->referenceKey, $values[0])
                ->array();
        } else {
            $referenceModel::select()
                ->where($this->referenceKey, in($values))
                ->array();
        }
    }

}
