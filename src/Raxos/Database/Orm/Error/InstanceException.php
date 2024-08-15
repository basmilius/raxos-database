<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Database\Error\DatabaseException;

/**
 * Class InstanceException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 13-08-2024
 */
final class InstanceException extends DatabaseException
{

    public const int ERR_IMMUTABLE = 1;
    public const int ERR_NOT_FOUND = 2;
    public const int ERR_FAILED_TO_GET = 4;
    public const int ERR_FAILED_TO_SET = 8;
    public const int ERR_NOT_A_FUNCTION = 16;

}
