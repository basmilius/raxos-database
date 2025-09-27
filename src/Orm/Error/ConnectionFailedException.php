<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class ConnectionFailedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class ConnectionFailedException extends Exception implements OrmExceptionInterface
{

    /**
     * ConnectionFailedException constructor.
     *
     * @param string $modelClass
     * @param DatabaseExceptionInterface $previous
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        DatabaseExceptionInterface $previous
    )
    {
        parent::__construct(
            'db_orm_connection_failed',
            "Cannot get a database connection for model {$this->modelClass}.",
            previous: $previous
        );
    }

}
