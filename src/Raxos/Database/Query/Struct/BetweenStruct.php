<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Error\QueryException;
use Stringable;
use function is_string;

/**
 * Class BetweenStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.5.0
 */
final readonly class BetweenStruct implements QueryStructInterface
{

    /**
     * BetweenStruct constructor.
     *
     * @param QueryLiteralInterface|Stringable|string|float|int $lower
     * @param QueryLiteralInterface|Stringable|string|float|int $upper
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public QueryLiteralInterface|Stringable|string|float|int $lower,
        public QueryLiteralInterface|Stringable|string|float|int $upper
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $lower = $this->normalize($connection, $this->lower);
        $upper = $this->normalize($connection, $this->upper);

        $query->raw("between {$lower} and {$upper}");
    }

    /**
     *
     *
     * @param ConnectionInterface $connection
     * @param QueryLiteralInterface|Stringable|string|float|int $value
     *
     * @return string
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    private function normalize(ConnectionInterface $connection, QueryLiteralInterface|Stringable|string|float|int $value): string
    {
        if ($value instanceof QueryLiteralInterface) {
            return (string)$value;
        }

        if (is_string($value) || $value instanceof Stringable) {
            try {
                return $connection->quote((string)$value);
            } catch (ConnectionException $err) {
                throw QueryException::connection($err);
            }
        }

        return (string)$value;
    }

}
