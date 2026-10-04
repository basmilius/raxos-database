<?php
declare(strict_types=1);

use Raxos\Database\Orm\Caster\JsonCaster;
use RaxosTests\Database\ParentModel;
use function RaxosTests\Database\unitOrm;

covers(JsonCaster::class);

beforeEach(function (): void {
    unitOrm();
    $this->model = ParentModel::singleOrFail(1);
});

it('round trips JSON scalar, compound and null values', function (mixed $value): void {
    $caster = new JsonCaster();
    expect($caster->decode($caster->encode($value, $this->model), $this->model))->toBe($value);
})->with([[['html' => '<script>', 'enabled' => false, 'count' => 0]], [42], [false], [null], ['héllo']]);

it('rejects values JSON cannot represent instead of silently losing data', function (): void {
    expect(fn() => new JsonCaster()->encode(INF, $this->model))->toThrow(JsonException::class);
});
