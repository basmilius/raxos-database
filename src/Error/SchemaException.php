<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Foundation\Error\ExceptionId;
use function sprintf;

/**
 * Class SchemaException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.17
 */
final class SchemaException extends DatabaseException
{

    /**
     * Returns a failed exception.
     *
     * @param DatabaseException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function failed(DatabaseException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_schema_failed',
            'Could not fetch the database schema due to an error.',
            $err
        );
    }

    /**
     * Returns an invalid table exception.
     *
     * @param string $name
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidTable(string $name): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_schema_error',
            sprintf('The table "%s" does not exist.', $name)
        );
    }

}
