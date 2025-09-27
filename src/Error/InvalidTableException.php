<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class InvalidTableException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 2.0.0
 */
final class InvalidTableException extends Exception implements DatabaseExceptionInterface
{

    /**
     * InvalidTableException constructor.
     *
     * @param string $table
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $table
    )
    {
        parent::__construct(
            'db_invalid_table',
            "Table {$this->table} does not exist."
        );
    }

}
