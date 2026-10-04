<?php
declare(strict_types=1);

use Raxos\Database\Orm\Backbone;
use RaxosTests\Database\{UnitAccount, UnitAddress};
use function RaxosTests\Database\unitAccounts;

covers(Backbone::class);

beforeEach(function (): void {
    $this->connection = unitAccounts();
});


it('tracks dirty values, resets derived caches on save and reloads database changes', function (): void {
    $model = UnitAccount::singleOrFail(1);
    $backbone = $model->backbone;
    expect($backbone->instance())->toBe($model)->and($backbone->createInstance()->backbone)->toBe($backbone)
        ->and($backbone->isModified())->toBeFalse()->and($backbone->getPrimaryKeyValues())->toBe([1]);
    expect($model->display)->toBe('BAS');
    $model->name = 'Updated';
    $model->settings = ['count' => 2];
    expect($backbone->isModified('name'))->toBeTrue()->and($backbone->isModified('secret'))->toBeFalse();
    $model->save();
    expect($model->display)->toBe('UPDATED')->and($backbone->isModified())->toBeFalse();
    $this->connection->execute("UPDATE unit_accounts SET display_name='Database' WHERE id=1");
    $backbone->reload();
    expect($model->name)->toBe('Database')->and($model->display)->toBe('DATABASE');
});

it('protects existing primary keys, immutable fields and macros with their original cause', function (string $property, mixed $value, string $cause): void {
    $model = UnitAccount::singleOrFail(1);
    try {
        $model->setValue($property, $value);
    } catch (Raxos\Database\Orm\Error\PropertyWriteFailedException $error) {
        expect($error->getPrevious())->toBeInstanceOf($cause)->and($model->backbone->currentInstance)->toBeNull();

        return;
    }
    test()->fail('Expected an immutable write error.');
})->with([
    ['id', 9, Raxos\Database\Orm\Error\ImmutablePrimaryKeyException::class],
    ['fixed', 'changed', Raxos\Database\Orm\Error\ImmutableException::class],
    ['display', 'changed', Raxos\Database\Orm\Error\ImmutableMacroException::class],
]);

it('round trips embedded changes and clears nullable embedded columns on save', function (): void {
    $model = UnitAccount::singleOrFail(1);
    $address = new UnitAddress();
    $address->city = 'Utrecht';
    $address->zip = '3500';
    $model->address = $address;
    $model->save();
    expect($this->connection->column('SELECT home_city FROM unit_accounts WHERE id=1'))->toBe('Utrecht');
    $model->address = null;
    $model->save();
    expect($model->address)->toBeNull()->and($this->connection->column('SELECT home_city FROM unit_accounts WHERE id=1'))->toBeNull();
});

it('runs queued save tasks in order once and reports missing reloaded records', function (): void {
    $model = UnitAccount::singleOrFail(1);
    $trace = [];
    $model->backbone->addSaveTask(function () use (&$trace): void {
        $trace[] = 1;
    });
    $model->backbone->addSaveTask(function () use (&$trace): void {
        $trace[] = 2;
    });
    $model->name = 'changed';
    $model->save();
    $model->save();
    expect($trace)->toBe([1, 2]);
    $this->connection->execute('DELETE FROM unit_accounts WHERE id=1');
    expect(fn() => $model->backbone->reload())->toThrow(Raxos\Database\Orm\Error\NotFoundException::class);
});
