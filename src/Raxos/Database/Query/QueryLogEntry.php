<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Raxos\Foundation\Collection\Arrayable;
use function array_key_exists;
use function array_shift;
use function debug_backtrace;
use function sprintf;

/**
 * Class QueryLogEntry
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
final class QueryLogEntry implements Arrayable, JsonSerializable
{

    private array $trace;

    /**
     * QueryLogEntry constructor.
     *
     * @param string $sql
     * @param float $queryTime
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(private string $sql, private float $queryTime)
    {
        $this->trace = debug_backtrace();

        array_shift($this->trace);
    }

    /**
     * Gets the query time.
     *
     * @return float
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getQueryTime(): float
    {
        return $this->queryTime;
    }

    /**
     * Gets the query sql.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Gets the trace.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'query_time' => 'float',
        'sql' => 'string',
        'trace' => 'string[]'
    ])]
    public final function toArray(): array
    {
        return [
            'query_time' => $this->queryTime,
            'sql' => $this->sql,
            'trace' => array_map(fn(array $item) => $this->formatTraceEntry($item), $this->trace)
        ];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'query_time' => 'float',
        'sql' => 'string',
        'trace' => 'string[]'
    ])]
    public final function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Formats a trace item to a string.
     *
     * @param array $item
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function formatTraceEntry(array $item): string
    {
        $file = $item['file'] ?? null;
        $line = $item['line'] ?? null;
        $call = null;

        if (array_key_exists('class', $item)) {
            $call = $item['class'] . $item['type'] . $item['function'];
        } else if (array_key_exists('function', $item)) {
            $call = $item['function'];
        }

        return sprintf('%s:%d (%s)', $file ?? '?', $line ?? -1, $call ?? '::unknown');
    }

}
