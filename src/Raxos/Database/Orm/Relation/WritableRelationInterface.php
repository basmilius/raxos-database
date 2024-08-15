<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\{RelationException, StructureException};

/**
 * Interface WritableRelationInterface
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 13-08-2024
 */
interface WritableRelationInterface
{

    /**
     * Writes to the relation and updates the appropriate properties.
     *
     * @param TDeclaringModel&Model $instance
     * @param RelationDefinition $property
     * @param TReferenceModel&Model|ModelArrayList<TReferenceModel&Model>|null $newValue
     *
     * @return void
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function write(Model $instance, RelationDefinition $property, Model|ModelArrayList|null $newValue): void;

}
