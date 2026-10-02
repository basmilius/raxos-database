<?php
declare(strict_types=1);

use Raxos\Database\Logger\{DeferredEvent, Logger, QueryEvent};
use Raxos\Foundation\Util\Stopwatch;

covers(DeferredEvent::class);

it('occupies its original position without printing until committed', function (): void {
    $logger = new Logger();
    $event = $logger->deferred();
    expect($event->index)->toBe(0)->and($event->trace)->toBe([])->and($event->print(true))->toBe('');
    $watch = new Stopwatch();
    $watch->run(static fn () => null);
    $event->commit(new QueryEvent('SELECT 9', $watch));
    expect($logger->count())->toBe(1)->and($logger->print())->toContain('SELECT 9');
});
