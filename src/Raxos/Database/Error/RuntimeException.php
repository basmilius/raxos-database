<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use function sprintf;

/**
 * Class RuntimeException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class RuntimeException extends DatabaseException
{

    public const int ERR_NOT_IMPLEMENTED = 1;
    public const int ERR_NOT_SUPPORTED = 2;

    /**
     * Returns a not implemented exception.
     *
     * @param string $class
     * @param string $method
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function notImplemented(string $class, string $method): self
    {
        return new RuntimeException(sprintf('Method "%s" is not implemented in "%s".', $method, $class), self::ERR_NOT_IMPLEMENTED);
    }

}
