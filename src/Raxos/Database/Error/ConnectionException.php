<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

/**
 * Class ConnectionException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class ConnectionException extends DatabaseException
{

    public const int ERR_INCOMPLETE_OPTIONS = 1;
    public const int ERR_UNDEFINED_CONNECTION = 2;
    public const int ERR_INVALID_CONNECTION = 4;
    public const int ERR_DISCONNECTED = 8;
    public const int ERR_SCHEMA_ERROR = 8;

    public const int ERR_ACCESS_DENIED = 1045;
    public const int ERR_ACCESS_DENIED_PASSWORD = 1698;

}
