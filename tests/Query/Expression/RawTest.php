<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\Raw;
use function RaxosTests\Database\{compiledExpression, unitQuery};

covers(Raw::class);

it('preserves explicitly trusted SQL and does not bind its contents', function (): void {
    expect(compiledExpression(new Raw('1 + 2')))->toBe(['1 + 2', []]);
    expect(unitQuery()->select(new Raw('1 + 2'))->statement()->fetchColumn())->toBe(3);
});
