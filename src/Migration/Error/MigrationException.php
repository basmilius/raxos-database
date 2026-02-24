<?php
declare(strict_types=1);

namespace Raxos\Database\Migration\Error;

use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;
use Throwable;

/**
 * Class MigrationException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Migration\Error
 * @since 1.9.0
 */
final class MigrationException extends Exception implements DatabaseExceptionInterface
{

    /**
     * MigrationException constructor.
     *
     * @param string $migration
     * @param string $message
     * @param Throwable|null $previous
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    public function __construct(
        public readonly string $migration,
        string $message,
        ?Throwable $previous = null
    )
    {
        parent::__construct(
            'db_migration_failed',
            $message,
            previous: $previous
        );
    }

}
