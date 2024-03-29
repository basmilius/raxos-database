<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

/**
 * Class ModelException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class ModelException extends DatabaseException
{

    public const int ERR_IMMUTABLE = 1;
    public const int ERR_NOT_FOUND = 2;
    public const int ERR_BAD_METHOD_CALL = 4;
    public const int ERR_CASTER_NOT_FOUND = 8;
    public const int ERR_FIELD_NOT_FOUND = 16;
    public const int ERR_MACRO_NOT_FOUND = 32;
    public const int ERR_MACRO_METHOD_NOT_FOUND = 64;
    public const int ERR_NO_TABLE_ASSIGNED = 128;
    public const int ERR_NOT_A_MODEL = 256;
    public const int ERR_NOT_SUPPORTED = 512;
    public const int ERR_RELATION_NOT_FOUND = 1024;
    public const int ERR_INVALID_TYPE = 2048;

}
