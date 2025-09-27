<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class ConnectionErrorException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class ConnectionErrorException extends Exception implements QueryExceptionInterface
{

    /**
     * ConnectionErrorException constructor.
     *
     * @param DatabaseExceptionInterface $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        private readonly DatabaseExceptionInterface $err
    )
    {
        parent::__construct(
            'db_query_connection_error',
            'Cannot prepare the query due to a connection error.',
            previous: $this->err
        );
    }

}
