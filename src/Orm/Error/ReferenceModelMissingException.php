<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Error\Exception;

/**
 * Class ReferenceModelMissingException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class ReferenceModelMissingException extends Exception implements OrmExceptionInterface
{

    /**
     * ReferenceModelMissingException constructor.
     *
     * @param RelationDefinition $property
     * @param StructureInterface $structure
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly RelationDefinition $property,
        public readonly StructureInterface $structure
    )
    {
        parent::__construct(
            'db_orm_reference_model_missing',
            "Cannot find the reference model for relation {$this->structure->class}->{$this->property->name}."
        );
    }

}
