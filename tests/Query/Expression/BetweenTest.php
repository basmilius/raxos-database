<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\Between;
use function RaxosTests\Database\{compiledExpression};

covers(Between::class);

it('binds both bounds while keeping the BETWEEN operator outside the parameters', function (): void {
    [$sql, $params] = compiledExpression(new Between("a' OR 1=1", '9'));
    expect($sql)->toMatch('/^between :p\d+_0 and :p\d+_1$/')->and($params)->toBe(["a' OR 1=1", '9']);
});
