<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use PDOException;
use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class SyntaxException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class SyntaxException extends Exception implements QueryExceptionInterface
{

    /**
     * SyntaxException constructor.
     *
     * @param string $sql
     * @param PDOException $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $sql,
        public readonly PDOException $err
    )
    {
        parent::__construct(
            'db_query_syntax',
            "Syntax error in query '{$sql}'.",
            previous: $err
        );
    }

}
