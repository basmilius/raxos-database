<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\Extract;
use function RaxosTests\Database\{compiledExpression};

covers(Extract::class);

it('keeps the datetime unit literal and binds the date input', function (): void {
    [$sql, $params] = compiledExpression(new Extract(Raxos\Database\Query\DateTimeUnit::YEAR, '2026-01-02'));
    expect($sql)->toMatch('/^extract\( year from :p\d+_0 \)$/')->and($params)->toBe(['2026-01-02']);
});
