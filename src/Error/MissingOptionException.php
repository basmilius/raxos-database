<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class MissingOptionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 2.0.0
 */
final class MissingOptionException extends Exception implements DatabaseExceptionInterface
{

    /**
     * MissingOptionException constructor.
     *
     * @param string $option
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $option
    )
    {
        parent::__construct(
            'db_missing_option',
            "Required option {$this->option} is missing."
        );
    }

}
