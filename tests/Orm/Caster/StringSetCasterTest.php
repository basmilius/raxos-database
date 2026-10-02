<?php
declare(strict_types=1);

use Raxos\Database\Orm\Caster\StringSetCaster;
use RaxosTests\Database\ParentModel;
use function RaxosTests\Database\unitOrm;

covers(StringSetCaster::class);

beforeEach(function (): void {
    unitOrm();
    $this->model = ParentModel::singleOrFail(1);
});


it('round trips nonempty sets including zero and handles unsupported storage values', function (): void {
    $caster = new StringSetCaster();
    expect($caster->decode($caster->encode(['first', '0', 'last'], $this->model), $this->model))->toBe(['first', '0', 'last'])
        ->and($caster->encode(['first', '0', 'last'], $this->model))->toBe('first,0,last')
        ->and($caster->decode('', $this->model))->toBe([])->and($caster->decode(null, $this->model))->toBe([])
        ->and($caster->decode(42, $this->model))->toBe([])->and($caster->encode('invalid', $this->model))->toBeNull();
});
