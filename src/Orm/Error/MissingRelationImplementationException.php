<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingRelationImplementationException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class MissingRelationImplementationException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingRelationImplementationException constructor.
     *
     * @param string $modelClass
     * @param string $propertyName
     * @param string $relationType
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $propertyName,
        public readonly string $relationType
    )
    {
        parent::__construct(
            'db_orm_missing_relation_implementation',
            "Implementation for relation {$this->relationType} of property {$this->modelClass}->{$this->propertyName} is missing."
        );
    }

}
