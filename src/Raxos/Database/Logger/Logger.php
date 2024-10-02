<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Foundation\Util\StopwatchUnit;
use function array_filter;
use function array_map;
use function array_sum;
use function count;
use function implode;

/**
 * Class Logger
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Logger
 * @since 1.0.16
 */
final class Logger
{

    private bool $enabled = false;

    /** @var Event[] */
    private array $events = [];

    /**
     * Returns the number of events.
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function count(): int
    {
        return count($this->events);
    }

    /**
     * Enables the logger.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disables the logger.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Returns TRUE if the logger is enabled.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Returns a new deferred event.
     *
     * @return DeferredEvent
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function deferred(): DeferredEvent
    {
        $index = count($this->events);

        return $this->events[] = new DeferredEvent($this, $index);
    }

    /**
     * Logs the given event.
     *
     * @param Event $event
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function log(Event $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Replaces the event at the given index.
     *
     * @param int $index
     * @param Event $event
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function replace(int $index, Event $event): void
    {
        $this->events[$index] = $event;
    }

    /**
     * Prints the logger report.
     *
     * @param bool $backtrace
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(bool $backtrace = false): string
    {
        $styles = $this->styles();

        $filteredEvents = array_filter($this->events, static fn(Event $event) => !($event instanceof EagerLoadEvent) || $event->events > 0);
        $events = implode("\n", array_map(static fn(Event $event) => $event->print($backtrace), $filteredEvents));
        $totalEagerLoads = count(array_filter($filteredEvents, static fn(Event $event) => $event instanceof EagerLoadEvent && $event->events > 0));
        $totalExecutionTime = array_sum(array_map(static fn(Event $event) => $event->stopwatch->as(StopwatchUnit::SECONDS), $filteredEvents));
        $totalQueries = count(array_filter($filteredEvents, static fn(Event $event) => $event instanceof QueryEvent));

        return <<<HTML
            <div id="_raxos_database_report">
                {$styles}
                
                <h1>Raxos Database Report</h1>
                <p>{$totalQueries} total queries | {$totalEagerLoads} eager loads | {$totalExecutionTime}s query time</p>
                
                <hr>
                
                <div class="_raxos_database_report_events">
                    {$events}
                </div>
            </div>
        HTML;
    }

    /**
     * Returns the styles.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    private function styles(): string
    {
        return <<<HTML
            <link rel="stylesheet" href="https://font.bmcdn.nl/css2?family=jetbrains-mono"/>
            
            <style>
                #_raxos_database_report, #_raxos_database_report::before, #_raxos_database_report::after { box-sizing: border-box; }
                #_raxos_database_report { position: fixed; top: 30px; right: 30px; height: 72px; width: 480px; padding: 15px; background: #111827; border-radius: 6px; box-shadow: 0 3px 9px rgb(0 0 0 / .25); color: #f3f4f6; font-family: jetbrains-mono, monospace; font-size: 12px; line-height: 1.5; overflow: auto; transition: 420ms ease-in-out; }
                #_raxos_database_report:hover { height: calc(100dvh - 60px); width: 60dvw; }
                #_raxos_database_report h1 { margin: 0; color: #22d3ee; font-size: 16px; line-height: 1; }
                #_raxos_database_report hr { height: 2px; margin-bottom: 9px; background: #374151; border: 0; }
                #_raxos_database_report abbr { text-decoration-color: #374151; text-underline-offset: 3px; }
                #_raxos_database_report ._raxos_database_report_events { display: flex; flex-flow: column; }
                #_raxos_database_report ._raxos_database_report_event { padding-top: 21px; padding-bottom: 21px; }
                #_raxos_database_report ._raxos_database_report_event:first-child { padding-top: 9px; }
                #_raxos_database_report ._raxos_database_report_event + ._raxos_database_report_event { border-top: 1px solid #1f2937; }
                #_raxos_database_report ._raxos_database_report_event strong { color: #14b8a6; }
                #_raxos_database_report ._raxos_database_report_event span { color: #374151; font-weight: 400; }
                #_raxos_database_report ._raxos_database_report_event strong span { color: #9ca3af; }
                #_raxos_database_report ._raxos_database_report_trace { margin-top: .5ch; margin-left: 2ch; color: #6b7280; font-size: 11px; }
            </style>
        HTML;

    }

}
