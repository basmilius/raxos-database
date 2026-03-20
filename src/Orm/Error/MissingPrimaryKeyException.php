<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingPrimaryKeyException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class MissingPrimaryKeyException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingPrimaryKeyException constructor.
     *
     * @param string $modelClass
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass
    )
    {
        parent::__construct(
            'db_orm_missing_primary_key',
            "Cannot save model {$this->modelClass}: no primary key defined."
        );
    }

}
