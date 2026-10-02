<?php
declare(strict_types=1);

use Raxos\Database\Orm\ReadonlyModel;
use RaxosTests\Database\{ParentModel, UnitAccount};
use function RaxosTests\Database\unitAccounts;

covers(ReadonlyModel::class);

beforeEach(function (): void {
    $this->connection = unitAccounts();
});


it('rejects writes, unsets and method calls while retaining read and visibility access', function (): void {
    $model = UnitAccount::singleOrFail(1)->readonly();
    expect($model->readonly())->toBe($model)->and($model->only('label')->toArray())->toBe(['label' => 'Bas']);
    expect(fn () => $model->setValue('name', 'Changed'))->toThrow(Raxos\Database\Orm\Error\ReadonlyModelException::class);
    expect(fn () => $model->unsetValue('name'))->toThrow(Raxos\Database\Orm\Error\ReadonlyModelException::class);
    expect(fn () => $model->save())->toThrow(Raxos\Database\Orm\Error\ReadonlyModelException::class);
});

it('returns readonly versions of related models and collections', function (): void {
    $model = ParentModel::singleOrFail(1)->readonly();
    expect($model->child)->toBeInstanceOf(ReadonlyModel::class)->and($model->children->first())->toBeInstanceOf(ReadonlyModel::class);
});
