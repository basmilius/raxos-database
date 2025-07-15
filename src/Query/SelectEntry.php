<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Error\QueryException;
use Stringable;
use function assert;

/**
 * Class SelectEntry
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.5.0
 */
final readonly class SelectEntry
{

    /**
     * SelectEntry constructor.
     *
     * @param QueryInterface|QueryLiteralInterface|QueryStructInterface|Stringable|string|int|float|bool $value
     * @param string|null $alias
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public QueryInterface|QueryLiteralInterface|QueryStructInterface|Stringable|string|int|float|bool $value,
        public ?string $alias = null
    ) {}

    /**
     * Unwraps the value of the entry.
     *
     * @param QueryInterface $query
     * @param ConnectionInterface $connection
     * @param GrammarInterface $grammar
     *
     * @return string
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     * @see Query::baseSelect()
     */
    public function unwrap(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): string
    {
        $alias = $this->alias !== null ? $grammar->escape($this->alias) : null;

        if ($this->value instanceof QueryInterface) {
            assert($alias !== null);

            return "({$this->value}) as {$alias}";
        }

        if ($this->value instanceof QueryStructInterface) {
            $query = new ($query::class)($connection);
            $this->value->compile($query, $connection, $grammar);

            return $alias !== null ? "({$query}) as {$alias}" : (string)$query;
        }

        if ($alias !== null) {
            return "{$this->value} as {$alias}";
        }

        if ($this->value instanceof QueryLiteralInterface) {
            return (string)$this->value;
        }

        return $this->value;
    }

}
