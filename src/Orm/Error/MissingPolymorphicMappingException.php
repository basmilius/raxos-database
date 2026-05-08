<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingPolymorphicMappingException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.3.0
 */
final class MissingPolymorphicMappingException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingPolymorphicMappingException constructor.
     *
     * @param string $modelClass
     * @param string $columnName
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $columnName
    )
    {
        parent::__construct(
            'db_orm_missing_polymorphic_mapping',
            "Cannot save model {$this->modelClass}, no discriminator value is mapped to it for column {$this->columnName}."
        );
    }

}
