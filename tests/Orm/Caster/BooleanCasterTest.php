<?php
declare(strict_types=1);

use Raxos\Database\Orm\Caster\BooleanCaster;
use RaxosTests\Database\ParentModel;
use function RaxosTests\Database\unitOrm;

covers(BooleanCaster::class);

beforeEach(function (): void {
    unitOrm();
    $this->model = ParentModel::singleOrFail(1);
});

it('decodes only database one values and preserves nullable storage', function (mixed $value, ?bool $expected): void {
    expect(new BooleanCaster()->decode($value, $this->model))->toBe($expected);
})->with([[1, true], ['1', true], [0, false], ['0', false], ['true', false], [null, null], [2, false]]);

it('encodes truthy and falsey application values as database integers', function (): void {
    $caster = new BooleanCaster();
    expect($caster->encode(true, $this->model))->toBe(1)->and($caster->encode(false, $this->model))->toBe(0)->and($caster->encode(null, $this->model))->toBe(0);
});
