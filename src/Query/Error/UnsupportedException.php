<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class UnsupportedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class UnsupportedException extends Exception implements QueryExceptionInterface
{

    /**
     * UnsupportedException constructor.
     *
     * @param string $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $err
    )
    {
        parent::__construct(
            'db_query_unsupported',
            $err
        );
    }

}
