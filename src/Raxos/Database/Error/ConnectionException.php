<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use PDOException;
use Raxos\Database\Contract\ConnectionInterface;
use Raxos\Foundation\Error\ExceptionId;
use function base_convert;
use function hash;
use function is_string;
use function sprintf;

/**
 * Class ConnectionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.17
 */
final class ConnectionException extends DatabaseException
{

    /**
     * Returns an invalid connection exception.
     *
     * @param string $id
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidConnection(string $id): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_invalid_connection',
            sprintf('Connection with id "%s" not found.', $id)
        );
    }

    /**
     * Returns an invalid implementation exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidImplementation(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_invalid_implementation',
            sprintf('Invalid connection class. Connection implementations should implement "%s".', ConnectionInterface::class)
        );
    }

    /**
     * Returns a missing option exception.
     *
     * @param string $option
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingOption(string $option): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_missing_option',
            sprintf('Missing required option "%s".', $option)
        );
    }

    /**
     * Returns a 'not connected' exception.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function notConnected(): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_not_connected',
            'Not connected to the database server.'
        );
    }

    /**
     * Returns an execution exception for the given code and message.
     *
     * @param int|string $code
     * @param string $message
     * @param PDOException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function of(int|string $code, string $message, PDOException $err): self
    {
        if (is_string($code)) {
            $code = (int)base_convert(hash('crc32', $code), 16, 10);
        }

        $code = PdoErrorCode::tryFrom($code) ?? PdoErrorCode::UNKNOWN;

        return new self(
            ExceptionId::for(__METHOD__ . $code->name),
            $code->getCode(),
            $message,
            $err
        );
    }

}
