<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class InvalidColumnException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class InvalidColumnException extends Exception implements OrmExceptionInterface
{

    /**
     * InvalidColumnException constructor.
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
            'db_orm_invalid_column',
            "Property {$this->modelClass->{$this->propertyName}} is not a valid column.",
        );
    }

}
