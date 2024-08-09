<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Foundation\Util\StopwatchUnit;
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
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(): string
    {
        $styles = $this->styles();

        $events = implode("\n", array_map(static fn(Event $event) => $event->print(), $this->events));
        $totalExecutionTime = array_sum(array_map(static fn(Event $event) => $event->stopwatch->as(StopwatchUnit::SECONDS), $this->events));

        return <<<HTML
            <div id="_raxos_database_report">
                {$styles}
                
                <h1>Raxos Database Report</h1>
                <p>Total execution time: {$totalExecutionTime}s</p>
                
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
                #_raxos_database_report { position: fixed; top: 30px; right: 30px; bottom: 30px; width: 35dvw; padding: 15px; background: #111827; border-top: 6px solid #22d3ee; color: #f3f4f6; font-family: jetbrains-mono, monospace; font-size: 12px; line-height: 1.4; overflow: auto; }
                #_raxos_database_report h1 { margin: 0; color: #22d3ee; font-size: 16px; line-height: 1; }
                #_raxos_database_report hr { height: 2px; margin-bottom: 9px; background: #374151; border: 0; }
                #_raxos_database_report ._raxos_database_report_events { display: flex; flex-flow: column; gap: 15px; }
                #_raxos_database_report ._raxos_database_report_event strong { color: #14b8a6; }
                #_raxos_database_report ._raxos_database_report_event span { color: #374151; font-weight: 400; }
                #_raxos_database_report ._raxos_database_report_event strong span { color: #9ca3af; }
                #_raxos_database_report ._raxos_database_report_trace { color: #6b7280; font-size: 10px; }
            </style>
        HTML;

    }

}
