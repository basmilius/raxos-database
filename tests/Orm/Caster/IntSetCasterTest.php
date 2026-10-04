<?php
declare(strict_types=1);

use Raxos\Database\Orm\Caster\IntSetCaster;
use RaxosTests\Database\ParentModel;
use function RaxosTests\Database\unitOrm;

covers(IntSetCaster::class);

beforeEach(function (): void {
    unitOrm();
    $this->model = ParentModel::singleOrFail(1);
});

it('round trips nonempty sets including zero and handles unsupported storage values', function (): void {
    $caster = new IntSetCaster();
    expect($caster->decode($caster->encode([1, 0, -2], $this->model), $this->model))->toBe([1, 0, -2])
        ->and($caster->encode([1, 0, -2], $this->model))->toBe('1,0,-2')
        ->and($caster->decode('', $this->model))->toBe([])->and($caster->decode(null, $this->model))->toBe([])
        ->and($caster->decode(42, $this->model))->toBe([])->and($caster->encode('invalid', $this->model))->toBeNull();
});
