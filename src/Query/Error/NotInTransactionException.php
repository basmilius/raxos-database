<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class NotInTransactionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class NotInTransactionException extends Exception implements QueryExceptionInterface
{

    /**
     * NotInTransactionException constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct()
    {
        parent::__construct(
            'db_query_not_in_transaction',
            'There is currently no active transaction.'
        );
    }

}
