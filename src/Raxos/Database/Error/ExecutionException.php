<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Foundation\Error\ExceptionId;
use function base_convert;
use function hash;

/**
 * Class ExecutionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.17
 */
final class ExecutionException extends DatabaseException
{

    /**
     * Returns an execution exception for the given code and message.
     *
     * @param int|string $code
     * @param string $message
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function of(int|string $code, string $message): self
    {
        if (is_string($code)) {
            $code = (int)base_convert(hash('crc32', $code), 16, 10);
        }

        $code = PdoErrorCode::tryFrom($code) ?? PdoErrorCode::UNKNOWN;

        return new self(
            ExceptionId::for(__METHOD__ . $code->name),
            $code->getCode(),
            $message
        );
    }

}
