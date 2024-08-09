<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Foundation\Util\{Stopwatch, StopwatchUnit};
use function array_shift;
use function array_slice;
use function count;
use function debug_backtrace;
use function explode;
use function implode;
use const DIRECTORY_SEPARATOR;

/**
 * Class Event
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Logger
 * @since 1.0.16
 */
abstract readonly class Event
{

    public array $trace;

    /**
     * Event constructor.
     *
     * @param Stopwatch $stopwatch
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public Stopwatch $stopwatch
    )
    {
        $trace = debug_backtrace();
        array_shift($trace);

        $this->trace = $trace;
    }

    /**
     * Prints the event.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public abstract function print(): string;

    /**
     * Returns the base.
     *
     * @param string|null $extra
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    protected final function printBase(?string $extra = null): string
    {
        $class = static::class;
        $time = $this->stopwatch->format(StopwatchUnit::SECONDS);
        $trace = $this->printTrace();

        return <<<HTML
            <div class="_raxos_database_report_event">
                <strong>{$class}<small> &mdash; {$time}</small><br><span>{$extra}</span></strong><br/>
                {$trace}
            </div>
        HTML;
    }

    /**
     * Returns the stack trace.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    protected final function printTrace(): string
    {
        $trace = [];

        foreach ($this->trace as $index => $item) {
            $file = $item['file'] ?? 'unknown file';
            $line = $item['line'] ?? 'unknown line';
            $call = 'unknown call';

            $file = explode(DIRECTORY_SEPARATOR, $file);
            $file = array_slice($file, -4);
            $file = '[...]' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $file);

            if (isset($item['class'])) {
                $call = $item['class'] . $item['type'] . $item['function'] . '(...)';
            } else if (isset($item['function'])) {
                $call = $item['function'] . '(...)';
            }

            $prefix = $index === count($this->trace) - 1 ? '└' : '├';

            $trace[] = <<<HTML
            {$prefix} {$call} <span>⟶ {$file}:{$line}</span>
            HTML;
        }

        $trace = implode('<br>', $trace);

        return <<<HTML
            <div class="_raxos_database_report_trace">
                {$trace}
            </div>
        HTML;

    }

}
