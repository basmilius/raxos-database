<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

/**
 * Class QueryException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class QueryException extends DatabaseException
{

    public const ERR_MISSING_FIELDS = 1;
    public const ERR_NO_TRANSACTION = 2;
    public const ERR_INVALID_MODEL = 4;
    public const ERR_NOT_IMPLEMENTED = 8;
    public const ERR_EAGER_NOT_AVAILABLE = 16;
    public const ERR_NO_RESULT = 32;
    public const ERR_PRIMARY_KEY_MISMATCH = 64;
    public const ERR_CLAUSE_NOT_DEFINED = 128;
    public const ERR_FIELD_NOT_FOUND = 256;

}
