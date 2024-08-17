<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

/**
 * Enum PdoErrorCode
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.17
 */
enum PdoErrorCode: int
{
    case UNKNOWN = -1;
    case ACCESS_DENIED = 1045;
    case ACCESS_DENIED_PASSWORD = 1698;
    case NO_SUCH_COLUMN = 1054;
    case NO_SUCH_TABLE = 1146;

    /**
     * Returns the exception code associated with the error.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getCode(): string
    {
        return match ($this) {
            self::UNKNOWN => 'unknown',
            self::ACCESS_DENIED, self::ACCESS_DENIED_PASSWORD => 'db_access_denied',
            self::NO_SUCH_COLUMN => 'db_no_such_column',
            self::NO_SUCH_TABLE => 'db_no_such_table'
        };
    }
}
