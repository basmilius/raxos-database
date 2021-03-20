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
        protected Connection $connection,
        protected string $referenceModel,
        protected bool $eagerLoad,
        protected string $fieldName
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
     * Eager loads relations.
     *
     * @param Model[] $models
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function eagerLoad(array $models): void;

    /**
     * Gets the field name.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Gets the referenced model.
     *
     * @template M of \Raxos\Database\Orm\Model
     *
     * @return class-string<M>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceModel(): string
    {
        return $this->referenceModel;
    }

    /**
     * Returns TRUE if the relation should have eager loading enabled
     * by default.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isEagerLoadEnabled(): bool
    {
        return $this->eagerLoad;
    }

}
