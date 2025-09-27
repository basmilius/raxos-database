<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingClauseException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class MissingClauseException extends Exception implements QueryExceptionInterface
{

    /**
     * MissingClauseException constructor.
     *
     * @param string $clause
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $clause
    )
    {
        parent::__construct(
            'db_query_missing_clause',
            "Clause {$this->clause} is missing from the query."
        );
    }

}
