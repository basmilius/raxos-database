<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\{Error\RelationException, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\RelationAttributeInterface;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Query\QueryInterface;

/**
 * Interface RelationInterface
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 *
 * @property RelationAttributeInterface $attribute
 * @property RelationDefinition $property
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 13-08-2024
 */
interface RelationInterface
{

    /**
     * Fetches the result of the relation.
     *
     * @param TDeclaringModel&Model $instance
     *
     * @return TReferenceModel&Model|ModelArrayList<TReferenceModel&Model>|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
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
     * @since 13-08-2024
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
     * @since 13-08-2024
     */
    public function rawQuery(): QueryInterface;

    /**
     * Eager loads the relation for the given instances.
     *
     * @param ModelArrayList<TDeclaringModel&Model> $instances
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function eagerLoad(ModelArrayList $instances): void;

}
