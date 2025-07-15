<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use BackedEnum;
use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Error\QueryException;
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;
use function array_map;
use function is_int;
use function is_string;

/**
 * Class InStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.5.0
 */
final readonly class InStruct implements QueryStructInterface
{

    /**
     * InStruct constructor.
     *
     * @param ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $values
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public ArrayableInterface|array $values
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $values = $this->values;

        if ($values instanceof ArrayableInterface) {
            $values = $values->toArray();
        }

        $values = array_map(fn(mixed $value) => $this->compileValue($connection, $value), $values);
        $values = implode(', ', $values);

        $query->raw("in({$values})");
    }

    /**
     * Compiles a single option value.
     *
     * @param ConnectionInterface $connection
     * @param mixed $value
     *
     * @return string
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    private function compileValue(ConnectionInterface $connection, mixed $value): string
    {
        if ($value instanceof QueryLiteralInterface || is_int($value)) {
            return (string)$value;
        }

        if ($value instanceof BackedEnum) {
            return is_string($value->value) ? "'{$value->value}'" : $value->value;
        }

        try {
            return $connection->quote($value);
        } catch (ConnectionException $err) {
            throw QueryException::connection($err);
        }
    }

}
