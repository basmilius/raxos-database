<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Throwable;
use function base_convert;
use function hash;
use function is_string;

/**
 * Class ConnectionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class ConnectionException extends DatabaseException
{

    public const int ERR_UNKNOWN = 0;
    public const int ERR_INCOMPLETE_OPTIONS = 1;
    public const int ERR_UNDEFINED_CONNECTION = 2;
    public const int ERR_INVALID_CONNECTION = 4;
    public const int ERR_DISCONNECTED = 8;
    public const int ERR_SCHEMA_ERROR = 8;

    public const int ERR_ACCESS_DENIED = 1045;
    public const int ERR_ACCESS_DENIED_PASSWORD = 1698;

    /**
     * Returns the exception for the given code.
     *
     * @param int|string $code
     * @param string $message
     * @param Throwable|null $previous
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public static function of(int|string $code, string $message, ?Throwable $previous = null): self
    {
        if (is_string($code)) {
            $code = (int)base_convert(hash('crc32', $code), 16, 10);
        }

        return match ($code) {
            self::ERR_ACCESS_DENIED => new self($message, self::ERR_ACCESS_DENIED, $previous),
            self::ERR_ACCESS_DENIED_PASSWORD => new self($message, self::ERR_ACCESS_DENIED_PASSWORD, $previous),
            default => new self('Unknown execution error.', self::ERR_UNKNOWN, $previous)
        };
    }

}
