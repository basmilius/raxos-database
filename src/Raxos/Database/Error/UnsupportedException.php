<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Foundation\Error\ExceptionId;

/**
 * Class UnsupportedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 21-01-2025
 */
final class UnsupportedException extends DatabaseException
{

    /**
     * Returns the exception for when optimizing tables is not supported by a database driver.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 21-01-2025
     */
    public static function optimizeTable(): self
    {
        return new self(
            ExceptionId::guess(),
            'db_unsupported_optimize_table',
            'The database driver does not support optimizing tables.'
        );
    }

    /**
     * Returns the exception for when truncating tables is not supported by a database driver.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 21-01-2025
     */
    public static function truncateTable(): self
    {
        return new self(
            ExceptionId::guess(),
            'db_unsupported_truncate_table',
            'The database driver does not support truncating tables.'
        );
    }

}
