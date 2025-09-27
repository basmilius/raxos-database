<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use PDOException;
use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class UnexpectedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class UnexpectedException extends Exception implements QueryExceptionInterface
{

    /**
     * UnexpectedException constructor.
     *
     * @param PDOException $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly PDOException $err
    )
    {
        parent::__construct(
            'db_query_unexpected',
            'Unexpected error while trying to execute query.',
            previous: $err
        );
    }

}
