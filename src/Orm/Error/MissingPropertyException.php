<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingPropertyException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class MissingPropertyException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingPropertyException constructor.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $propertyName
    )
    {
        parent::__construct(
            'db_orm_missing_property',
            "Cannot get property {$this->modelClass}->{$this->propertyName} because it does not exist."
        );
    }

}
