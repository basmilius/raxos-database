<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Foundation\Error\RaxosException;
use Throwable;
use function base_convert;
use function hash;
use function is_string;

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
     * @param int|string $code
     * @param string $message
     * @param Throwable|null $err
     *
     * @return DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function throw(int|string $code, string $message, ?Throwable $err = null): DatabaseException
    {
        if (is_string($code)) {
            $code = (int)base_convert(hash('crc32', $code), 16, 10);
        }

        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
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
