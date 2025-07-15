<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\{RelationException, StructureException};

/**
 * Interface RelationInterface
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.0.17
 */
interface RelationInterface
{

    public RelationAttributeInterface $attribute {
        get;
    }

    public RelationDefinition $property {
        get;
    }

    /**
     * Fetches the result of the relation.
     *
     * @param TDeclaringModel&Model $instance
     *
     * @return TReferenceModel&Model|ModelArrayList<int, TReferenceModel&Model>|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function fetch(Model $instance): Model|ModelArrayList|null;

    /**
     * Returns a prepared query for the relation.
     *
     * @param TDeclaringModel&Model $instance
     *
     * @return QueryInterface<TReferenceModel&Model>
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function query(Model $instance): QueryInterface;

    /**
     * Returns a raw unprepared query for the relation.
     *
     * @return QueryInterface
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function rawQuery(): QueryInterface;

    /**
     * Eager loads the relation for the given instances.
     *
     * @param ModelArrayList<int, TDeclaringModel&Model> $instances
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function eagerLoad(ModelArrayList $instances): void;

}
