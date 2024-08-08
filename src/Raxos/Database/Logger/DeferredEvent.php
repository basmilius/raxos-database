<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Foundation\Util\Stopwatch;

/**
 * Class DeferredEvent
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Logger
 * @since 1.0.16
 */
final readonly class DeferredEvent extends Event
{

    /**
     * DeferredEvent constructor.
     *
     * @param Logger $logger
     * @param int $index
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     * @internal
     * @private
     */
    public function __construct(
        private Logger $logger,
        private int $index
    )
    {
        parent::__construct(new Stopwatch());
    }

    /**
     * Commits the given event.
     *
     * @param Event $event
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function commit(Event $event): void
    {
        $this->logger->replace($this->index, $event);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(): string
    {
        return '';
    }

}
