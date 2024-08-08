<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Foundation\Util\Stopwatch;

/**
 * Class QueryEvent
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Logger
 * @since 1.0.16
 */
final readonly class QueryEvent extends Event
{

    /**
     * QueryEvent constructor.
     *
     * @param string $query
     * @param Stopwatch $stopwatch
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public string $query,
        Stopwatch $stopwatch
    )
    {
        parent::__construct($stopwatch);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(): string
    {
        return $this->printBase($this->query);
    }

}
