<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class InvalidOptionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 2.0.0
 */
final class InvalidOptionException extends Exception implements DatabaseExceptionInterface
{

    /**
     * InvalidOptionException constructor.
     *
     * @param string $option
     * @param string $message
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $option,
        string $message
    )
    {
        parent::__construct(
            'db_invalid_option',
            "Invalid option {$this->option}: {$message}"
        );
    }

}
