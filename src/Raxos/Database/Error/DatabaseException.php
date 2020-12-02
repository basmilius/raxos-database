<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Foundation\Error\RaxosException;
use Throwable;

/**
 * Class DatabaseException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
abstract class DatabaseException extends RaxosException
{

    /**
     * Throws a database exception based on the given code and message.
     *
     * @param int $code
     * @param string $message
     * @param Throwable|null $err
     *
     * @return DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function throw(int $code, string $message, ?Throwable $err = null): DatabaseException
    {
        switch ($code) {
            case ConnectionException::ERR_ACCESS_DENIED:
            case ConnectionException::ERR_ACCESS_DENIED_PASSWORD:
                return new ConnectionException($message, $code);

            case SchemaException::ERR_NO_SUCH_COLUMN:
            case SchemaException::ERR_NO_SUCH_TABLE:
                return new SchemaException($message, $code, $err);

            default:
                return new RuntimeException($message, $code, $err);
        }
    }

}
