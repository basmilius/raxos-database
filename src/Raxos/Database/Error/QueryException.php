<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use PDOException;
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Foundation\Error\ExceptionId;
use function sprintf;

/**
 * Class QueryException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.17
 */
final class QueryException extends DatabaseException
{

    /**
     * Returns a connection exception.
     *
     * @param ConnectionException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function connection(ConnectionException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_connection',
            'Got a connection exception.',
            $err
        );
    }

    /**
     * Returns an incomplete exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function incomplete(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_incomplete',
            'There must be at least one column'
        );
    }

    /**
     * Returns an invalid exception.
     *
     * @param string $message
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalid(string $message): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_invalid',
            $message
        );
    }

    /**
     * Returns an invalid exception.
     *
     * @param string $message
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidModel(string $message): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_invalid_model',
            $message
        );
    }

    /**
     * Returns a missing alias exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingAlias(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_missing_alias',
            'The query performs a select that requires an alias.'
        );
    }

    /**
     * Returns a missing clause exception.
     *
     * @param string $clause
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingClause(string $clause): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_missing_clause',
            sprintf('Clause "%s" is missing in the query.', $clause)
        );
    }

    /**
     * Returns a missing model exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingModel(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_missing_model',
            'The query needs a model in order to use relational queries.'
        );
    }

    /**
     * Returns a missing result exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingResult(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_missing_result',
            'The query did not return any result.'
        );
    }

    /**
     * Returns a 'not in transaction' exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function notInTransaction(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_not_in_transaction',
            'No active transaction.'
        );
    }

    /**
     * Returns a primary key mismatch exception for too few values.
     *
     * @param string $modelClass
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function primaryKeyMismatchTooFew(string $modelClass): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_primary_key_mismatch',
            sprintf('Too few primary key values for model "%s".', $modelClass)
        );
    }

    /**
     * Returns a primary key mismatch exception for too many values.
     *
     * @param string $modelClass
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function primaryKeyMismatchTooMany(string $modelClass): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_primary_key_mismatch',
            sprintf('Too many primary key values for model "%s".', $modelClass)
        );
    }

    /**
     * Returns a structure exception.
     *
     * @param string $modelClass
     * @param StructureException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public static function structure(string $modelClass, StructureException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_structure',
            sprintf('Something went wrong during the structure generation process for model "%s".', $modelClass),
            $err
        );
    }

    /**
     * Returns the exception for when a syntax error is found in a query.
     *
     * @param string $sql
     * @param PDOException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public static function syntaxError(string $sql, PDOException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_syntax_error',
            sprintf('Syntax error in query: %s.', $sql),
            $err
        );
    }

    /**
     * Returns an unsupported exception.
     *
     * @param string $message
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function unsupported(string $message): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_query_unsupported',
            $message
        );
    }

}
