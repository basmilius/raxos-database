<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Connection\Connection;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Query;

/**
 * Class Relation
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.0
 */
abstract class Relation
{

    /**
     * Relation constructor.
     *
     * @template M of \Raxos\Database\Orm\Model
     *
     * @param Connection $connection
     * @param class-string<M> $referenceModel
     * @param bool $eagerLoad
     * @param string $fieldName
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly Connection $connection,
        public readonly string $referenceModel,
        public readonly bool $eagerLoad,
        public readonly string $fieldName
    )
    {
    }

    /**
     * Gets the referenced rows.
     *
     * @param Model $model
     *
     * @return Model|Model[]|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function get(Model $model): Model|ModelArrayList|null;

    /**
     * Gets the query.
     *
     * @param Model $model
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function getQuery(Model $model): Query;

    /**
     * Gets the query for raw use (without a model).
     *
     * @param class-string<Model> $modelClass
     * @param bool $isPrepared
     *
     * @return Query
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function getRaw(string $modelClass, bool $isPrepared): Query;

    /**
     * Eager loads relations.
     *
     * @param Model[] $models
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function eagerLoad(array $models): void;

}
