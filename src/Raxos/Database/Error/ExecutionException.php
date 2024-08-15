<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Throwable;
use function base_convert;
use function hash;
use function is_string;

/**
 * Class ExecutionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 14-08-2024
 */
final class ExecutionException extends DatabaseException
{

    public const int ERR_UNKNOWN = 0;

    public const int ERR_NO_SUCH_COLUMN = 1054;
    public const int ERR_NO_SUCH_TABLE = 1146;

    /**
     * Returns the exception for the given code.
     *
     * @param int|string $code
     * @param string $message
     * @param Throwable|null $previous
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public static function of(int|string $code, string $message, ?Throwable $previous = null): self
    {
        if (is_string($code)) {
            $code = (int)base_convert(hash('crc32', $code), 16, 10);
        }

        return match ($code) {
            self::ERR_NO_SUCH_COLUMN => new self($message, self::ERR_NO_SUCH_COLUMN, $previous),
            self::ERR_NO_SUCH_TABLE => new self($message, self::ERR_NO_SUCH_TABLE, $previous),
            default => new self('Unknown execution error.', self::ERR_UNKNOWN, $previous)
        };
    }

}
