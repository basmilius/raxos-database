<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

/**
 * Class SchemaException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class SchemaException extends DatabaseException
{

    public const ERR_NO_SUCH_COLUMN = 1054;
    public const ERR_NO_SUCH_TABLE = 1146;

}
