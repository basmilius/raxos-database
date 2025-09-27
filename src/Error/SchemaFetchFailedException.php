<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class SchemaFetchFailedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 2.0.0
 */
final class SchemaFetchFailedException extends Exception implements DatabaseExceptionInterface
{

    /**
     * SchemaFetchFailedException constructor.
     *
     * @param DatabaseExceptionInterface $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly DatabaseExceptionInterface $err
    )
    {
        parent::__construct(
            'db_schema_fetch_failed',
            'Cannot fetch the database schema due to an error.',
            previous: $this->err
        );
    }

}
