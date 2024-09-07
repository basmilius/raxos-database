<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Foundation\Util\{Stopwatch, StopwatchUnit, StringUtil};
use function array_shift;
use function array_slice;
use function count;
use function debug_backtrace;
use function explode;
use function implode;
use function in_array;
use function str_ends_with;
use function str_starts_with;
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
        array_shift($trace);

        $this->trace = $trace;
    }

    /**
     * Prints the event.
     *
     * @param bool $backtrace
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public abstract function print(bool $backtrace): string;

    /**
     * Returns the base.
     *
     * @param string|null $extra
     * @param bool $backtrace
     * @param string|null $time
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    protected final function printBase(?string $extra = null, bool $backtrace = false, ?string $time = null): string
    {
        $class = StringUtil::shortClassName(static::class);
        $time ??= $this->stopwatch->format(StopwatchUnit::SECONDS);
        $trace = $backtrace ? $this->printTrace() : '';

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
        $functionsToIgnore = ['print_r', 'var_dump'];
        $trace = [];

        foreach ($this->trace as $index => $item) {
            $file = $item['file'] ?? 'unknown file';
            $line = $item['line'] ?? 'unknown line';
            $call = 'unknown call';

            $file = explode(DIRECTORY_SEPARATOR, $file);
            $file = array_slice($file, -4);
            $file = '[...]' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $file);

            if (str_ends_with($file, '/dev/server.php')) {
                continue;
            }

            if (isset($item['class'])) {
                $class = StringUtil::shortClassName($item['class']);

                // note: By-default, we don't want to show the implementing side our internal Raxos stuff.
                if (str_starts_with($item['class'], 'Raxos\\')) {
                    continue;
                }

                $call = "<abbr title='{$item['class']}'>{$class}</abbr>" . $item['type'] . $item['function'] . '(...)';
            } elseif (isset($item['function'])) {
                if (in_array($item['function'], $functionsToIgnore)) {
                    continue;
                }

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
