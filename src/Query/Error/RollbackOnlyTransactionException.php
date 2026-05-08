<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class RollbackOnlyTransactionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.3.0
 */
final class RollbackOnlyTransactionException extends Exception implements QueryExceptionInterface
{

    /**
     * RollbackOnlyTransactionException constructor.
     *
     * @param string|null $connectionId
     * @param int $transactionDepth
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    public function __construct(
        public readonly ?string $connectionId = null,
        public readonly int $transactionDepth = 0
    )
    {
        $context = $connectionId !== null
            ? " (connection: {$connectionId}, depth at rollback: {$transactionDepth})"
            : '';

        parent::__construct(
            'db_query_rollback_only_transaction',
            "The outer transaction was rolled back because a nested savepoint was rolled back. Outer commit is not safe in this state{$context}."
        );
    }

}
