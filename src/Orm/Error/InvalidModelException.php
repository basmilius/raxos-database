<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class InvalidModelException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class InvalidModelException extends Exception implements OrmExceptionInterface
{

    /**
     * InvalidModelException constructor.
     *
     * @param string $className
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $className
    )
    {
        parent::__construct(
            'db_orm_invalid_model',
            "Cannot create a model structure for class {$this->className}, the class is not a valid model.",
        );
    }

}
