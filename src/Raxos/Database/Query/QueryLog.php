<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Raxos\Foundation\Collection\Arrayable;
use function array_map;
use function array_sum;
use function count;

/**
 * Class QueryLog
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
final class QueryLog implements Arrayable, JsonSerializable
{

    private array $queries = [];

    /**
     * Adds the given query log entry.
     *
     * @param QueryLogEntry $entry
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function addQuery(QueryLogEntry $entry): void
    {
        $this->queries[] = $entry;
    }

    /**
     * Gets the logged queries.
     *
     * @return QueryLogEntry[]
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'queries' => 'array',
        'total_queries' => 'int',
        'total_query_time' => 'float'
    ])]
    public final function toArray(): array
    {
        return [
            'total_query_time' => array_sum(array_map(fn(QueryLogEntry $e) => $e->getQueryTime(), $this->queries)),
            'total_queries' => count($this->queries),
            'queries' => $this->queries
        ];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'queries' => 'array',
        'total_query_time' => 'float'
    ])]
    public final function jsonSerialize(): array
    {
        return $this->toArray();
    }

}
