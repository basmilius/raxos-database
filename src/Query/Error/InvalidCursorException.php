<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class InvalidCursorException
 *
 * Reports malformed cursors and cursors that do not belong to the current query.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 3.3.0
 */
final class InvalidCursorException extends Exception implements QueryExceptionInterface
{
    /**
     * Rejects cursor input without exposing query bindings or database details.
     *
     * @param string $message
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public function __construct(string $message)
    {
        parent::__construct('db_query_invalid_cursor', $message);
    }
}
