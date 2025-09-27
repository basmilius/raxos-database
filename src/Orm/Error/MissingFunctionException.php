<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingFunctionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class MissingFunctionException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingFunctionException constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $functionName
    )
    {
        parent::__construct(
            'db_orm_missing_function',
            "Invocation of function {$this->functionName}() failed on model {$this->modelClass}. An implementation for the function is missing."
        );
    }

}
