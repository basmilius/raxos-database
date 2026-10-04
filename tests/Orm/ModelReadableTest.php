<?php
declare(strict_types=1);

use Raxos\Database\Orm\ModelReadable;
use RaxosTests\Database\{UnitAccount, UnitAccountMacros, UnitAccountState};
use function RaxosTests\Database\unitAccounts;

covers(ModelReadable::class);

beforeEach(function (): void {
    $this->connection = unitAccounts();
});

it('serializes aliases, defaults, nullable enums, embedded values and cached macros', function (): void {
    $model = UnitAccount::singleOrFail(1);
    $data = $model->toArray();
    expect($data['label'])->toBe('Bas')->and($data)->not->toHaveKey('name')->not->toHaveKey('secret')
        ->and($data['enabled'])->toBeFalse()->and($data['state'])->toBe(UnitAccountState::Active)
        ->and($data['address'])->toBe(['city' => 'Amsterdam', 'postal' => '1000'])->and($data['display'])->toBe('BAS');
    $model->toArray();
    expect(UnitAccountMacros::$calls)->toBe(1)->and((string)$model)->toContain('(1)')->and($model->__debugInfo())->toBe($data);
    $other = UnitAccount::singleOrFail(2);
    expect($other->address)->toBeNull()->and($other->state)->toBeNull()->and($other->settings)->toBeNull();
});

it('applies alias-aware visibility to clones without changing the underlying model', function (): void {
    $model = UnitAccount::singleOrFail(1);
    expect($model->only('label')->toArray())->toBe(['label' => 'Bas'])
        ->and($model->makeHidden('label')->toArray())->not->toHaveKey('label')
        ->and($model->makeVisible('secret')->toArray()['secret'])->toBe('secret')
        ->and($model->toArray())->toHaveKey('label')->not->toHaveKey('secret')
        ->and($model->makeVisible('secret')->makeHidden('secret')->toArray())->not->toHaveKey('secret');
});
