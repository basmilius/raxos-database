<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\{Attribute\RelationAttributeInterface, Definition\ColumnDefinition, Model, ModelArrayList};
use Raxos\Database\Query\QueryInterface;
use Raxos\Foundation\Collection\ArrayList;

/**
 * Interface RelationInterface
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 *
 * @property RelationAttributeInterface $attribute
 * @property ColumnDefinition $column
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
interface RelationInterface
{

    /**
     * Fetches the result of the relation.
     *
     * @param TDeclaringModel&Model $instance
     *
     * @return TReferenceModel&Model|ModelArrayList<TReferenceModel&Model>|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function fetch(Model $instance): Model|ModelArrayList|null;

    /**
     * Returns a prepared query for the relation.
     *
     * @param TDeclaringModel&Model $instance
     *
     * @return QueryInterface<TReferenceModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function query(Model $instance): QueryInterface;

    /**
     * Returns a raw unprepared query for the relation.
     *
     * @return QueryInterface<TReferenceModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function rawQuery(): QueryInterface;

    /**
     * Eager loads the relation for the given instances.
     *
     * @param ArrayList<int, TDeclaringModel> $instances
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function eagerLoad(ArrayList $instances): void;

}
