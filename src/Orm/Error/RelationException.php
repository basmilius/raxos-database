<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Database\Contract\StructureInterface;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Foundation\Error\ExceptionId;
use function sprintf;

/**
 * Class RelationException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 1.0.17
 */
final class RelationException extends OrmException
{

    /**
     * Returns a reference model missing exception.
     *
     * @param RelationDefinition $property
     * @param StructureInterface $structure
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function referenceModelMissing(RelationDefinition $property, StructureInterface $structure): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_reference_model_missing',
            sprintf('Could not find reference model for relation "%s" of model "%s".', $property->name, $structure->class)
        );
    }

}
