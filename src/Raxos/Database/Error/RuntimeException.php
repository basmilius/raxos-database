<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

/**
 * Class RuntimeException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.0.0
 */
final class RuntimeException extends DatabaseException
{

    public const int ERR_NOT_IMPLEMENTED = 1;
    public const int ERR_NOT_SUPPORTED = 2;

}
