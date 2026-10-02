<?php
declare(strict_types=1);

use Raxos\Database\Logger\QueryEvent;
use Raxos\Foundation\Util\Stopwatch;
use function RaxosTests\Database\unitQuery;

covers(QueryEvent::class);

it('escapes raw SQL and bound values in the HTML report including the backtrace', function (): void {
    $watch = new Stopwatch();
    $watch->run(static fn () => null);
    $raw = new QueryEvent('SELECT "<script>bad</script>"', $watch);
    expect($raw->print(true))->toContain('&lt;script&gt;bad&lt;/script&gt;')->not->toContain('<script>')->toContain('_raxos_database_report_trace');
    $query = unitQuery()->select(1)->where(Raxos\Database\Query\Literal\Literal::of(1), '<img src=x onerror=alert(1)>');
    $event = new QueryEvent($query, $watch);
    expect($event->print(true))->toContain('&lt;img')->not->toContain('<img')->toContain('<abbr title=');
});
