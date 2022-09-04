<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Raxos\Foundation\Collection\Arrayable;
use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_shift;
use function count;
use function debug_backtrace;
use function sprintf;
use function str_pad;
use function strlen;
use const STR_PAD_LEFT;

/**
 * Class QueryLogEntry
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
final class QueryLogEntry implements Arrayable, JsonSerializable
{

    public readonly array $trace;

    /**
     * QueryLogEntry constructor.
     *
     * @param string $sql
     * @param float $queryTime
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly string $sql,
        public readonly float $queryTime
    )
    {
        $trace = debug_backtrace();

        array_shift($trace);

        $this->trace = $trace;
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
            'trace' => array_combine(
                array_map(fn(int $key) => str_pad((string)$key, strlen((string)count($this->trace)), pad_type: STR_PAD_LEFT), array_keys($this->trace)),
                array_map(fn(array $item) => $this->formatTraceEntry($item), $this->trace)
            )
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

        return sprintf('%s (%s:%d)', str_pad(($call ?? '::unknown') . ' ', 72, '-'), $file ?? '?', $line ?? -1);
    }

}
