<?php
declare(strict_types=1);

use Raxos\Database\Orm\Model;
use RaxosTests\Database\{ParentModel, UnitAccount, UnitAccountState};
use function RaxosTests\Database\unitAccounts;

covers(Model::class);

beforeEach(function (): void {
    $this->connection = unitAccounts();
});


it('writes new and dirty models and shares changes with visibility and readonly views', function (): void {
    $model = UnitAccount::singleOrFail(1);
    $view = $model->readonly();
    $model->name = 'Changed';
    $model->save();
    expect($view->name)->toBe('Changed')->and($model->backbone->currentInstance)->toBeNull()
        ->and($this->connection->column('SELECT display_name FROM unit_accounts WHERE id=1'))->toBe('Changed');
    $created = new UnitAccount();
    $created->name = 'Created';
    $created->secret = 'hidden';
    $created->fixed = 'new';
    $created->state = UnitAccountState::Disabled;
    $created->settings = ['enabled' => false];
    $created->save();
    expect($created->id)->toBe(3)->and($created->doubled)->toBe(6)->and($created->backbone->isNew)->toBeFalse()
        ->and($created->backbone->isModified())->toBeFalse()->and(UnitAccount::single(3)->backbone)->toBe($created->backbone);
    $created->destroy();
    expect(UnitAccount::single(3))->toBeNull();
});

it('queries declared relations and rejects calls to ordinary properties', function (): void {
    $parent = ParentModel::singleOrFail(1);
    expect($parent->children()->resultCount())->toBe(2);
    expect(fn() => $parent->name())->toThrow(Raxos\Database\Orm\Error\MissingFunctionException::class);
    expect($parent->backbone->currentInstance)->toBeNull();
});
