<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;
use Throwable;
use function base_convert;
use function hash;
use function is_string;

/**
 * Class ExecutionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 2.0.0
 */
final class ExecutionException extends Exception implements DatabaseExceptionInterface
{

    /**
     * ExecutionException constructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        int|string $code,
        string $message,
        ?Throwable $previous = null
    )
    {
        if (is_string($code)) {
            $code = (int)base_convert(hash('crc32', $code), 16, 10);
        }

        $code = PdoErrorCode::tryFrom($code) ?? PdoErrorCode::UNKNOWN;

        parent::__construct(
            $code->getCode(),
            $message,
            previous: $previous
        );
    }

}
