<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Database\Error\DatabaseException;

/**
 * Class StructureException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 13-08-2024
 */
final class StructureException extends DatabaseException
{

    public const int ERR_NOT_A_MODEL = 1;
    public const int ERR_REFLECTION_ERROR = 2;
    public const int ERR_TABLE_MISSING = 4;
    public const int ERR_INVALID_CASTER = 8;
    public const int ERR_INVALID_MACRO = 16;
    public const int ERR_INVALID_RELATION = 32;
    public const int ERR_UNKNOWN_PROPERTY = 64;
    public const int ERR_CONNECTION_FAILED = 128;
    public const int ERR_POLYMORPHIC_COLUMN_MISSING = 256;

}
