<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Database\Contract\{ConnectionInterface, QueryInterface, QueryLiteralInterface};
use Raxos\Database\Error\{ConnectionException, QueryException};
use function is_int;

/**
 * Class StructHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.6.1
 * @internal
 * @private
 */
final class StructHelper
{

    /**
     * Compiles a value.
     *
     * @param ConnectionInterface $connection
     * @param mixed $value
     *
     * @return string|int
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public static function compileValue(ConnectionInterface $connection, mixed $value): string|int
    {
        if ($value instanceof QueryInterface) {
            return "({$value})";
        }

        if ($value instanceof QueryLiteralInterface || is_int($value)) {
            return (string)$value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        try {
            return $connection->quote($value);
        } catch (ConnectionException $err) {
            throw QueryException::connection($err);
        }
    }

}
