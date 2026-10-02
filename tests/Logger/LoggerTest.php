<?php
declare(strict_types=1);

use Raxos\Database\Logger\{Logger, QueryEvent};
use Raxos\Foundation\Util\Stopwatch;

covers(Logger::class);

it('tracks enablement, deferred replacement and aggregate query counts', function (): void {
    $logger = new Logger();
    expect($logger->enabled)->toBeFalse()->and($logger->count())->toBe(0);
    $logger->enable();
    expect($logger->enabled)->toBeTrue();
    $watch = new Stopwatch();
    $watch->run(static fn () => null);
    $pending = $logger->deferred();
    $logger->log(new QueryEvent('SELECT 2', $watch));
    $pending->commit(new QueryEvent('SELECT 1', $watch));
    expect($logger->count())->toBe(2)->and($logger->print())->toContain('2 total queries')->toContain('SELECT 1')->toContain('SELECT 2');
    $logger->disable();
    expect($logger->enabled)->toBeFalse();
});
