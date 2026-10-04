<?php
declare(strict_types=1);

use Raxos\Database\Orm\Caster\FloatCaster;
use RaxosTests\Database\ParentModel;
use function RaxosTests\Database\unitOrm;

covers(FloatCaster::class);

beforeEach(function (): void {
    unitOrm();
    $this->model = ParentModel::singleOrFail(1);
});

it('round trips decimals, zero and nullable storage', function (mixed $value, ?float $expected): void {
    $caster = new FloatCaster();
    expect($caster->decode($value, $this->model))->toBe($expected);
    expect($caster->encode($expected, $this->model))->toBe($expected === null ? null : (string)$expected);
})->with([['1.25', 1.25], [0, 0.0], [-2.5, -2.5], [null, null]]);
