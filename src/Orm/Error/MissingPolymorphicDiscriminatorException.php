<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingPolymorphicDiscriminatorException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class MissingPolymorphicDiscriminatorException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingPolymorphicDiscriminatorException constructor.
     *
     * @param string $modelClass
     * @param string $columnName
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $columnName
    )
    {
        parent::__construct(
            'db_orm_missing_polymorphic_discriminator',
            "Cannot create a new instance of model {$this->modelClass}, discriminator column {$this->columnName} is missing from the result."
        );
    }

}
