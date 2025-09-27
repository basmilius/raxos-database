<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Reflection\ReflectionFailedExceptionInterface;
use Raxos\Error\Exception;
use ReflectionException;

/**
 * Class ReflectionErrorException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class ReflectionErrorException extends Exception implements OrmExceptionInterface, ReflectionFailedExceptionInterface
{

    /**
     * ReflectionErrorException constructor.
     *
     * @param string $modelClass
     * @param ReflectionException $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly ReflectionException $err
    )
    {
        parent::__construct(
            'db_orm_reflection_error',
            "Cannot create a structure for model {$this->modelClass} due to a reflection error.",
            previous: $err
        );
    }

}
