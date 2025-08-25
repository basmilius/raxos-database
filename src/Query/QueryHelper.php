<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Database\Contract\{QueryInterface, QueryLiteralInterface, QueryStructInterface, QueryValueInterface};
use Raxos\Database\Error\QueryException;
use Stringable;
use function is_bool;
use function is_string;

/**
 * Class QueryHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.0.0
 */
final class QueryHelper
{

    /**
     * Unwraps a value.
     *
     * @param QueryInterface $query
     * @param BackedEnum|Stringable|QueryValueInterface|string|int|float|bool $value
     *
     * @return void
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public static function value(QueryInterface $query, BackedEnum|Stringable|QueryValueInterface|string|int|float|bool $value): void
    {
        match (true) {
            $value instanceof BackedEnum => match (true) {
                is_string($value->value) => $query->raw((string)stringLiteral($value->value)),
                default => $query->raw((string)literal($value->value)),
            },

            $value instanceof QueryStructInterface => $value->compile($query, $query->connection, $query->grammar),

            $value instanceof QueryLiteralInterface => $query->raw((string)$value),

            $value instanceof Stringable => $query->raw((string)stringLiteral($value)),

            is_bool($value) => $query->raw($value ? '1' : '0'),

            default => $query->raw((string)$query->addParam($value))
        };
    }

}
