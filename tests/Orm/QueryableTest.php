<?php
declare(strict_types=1);

use Raxos\Database\Orm\Queryable;
use RaxosTests\Database\{UnitAccount};
use function RaxosTests\Database\unitAccounts;

covers(Queryable::class);

beforeEach(function (): void {
    $this->connection = unitAccounts();
});

it('keeps column mappings separate for model views over the same table', function (): void {
    expect(UnitAccount::col('name')->column)->toBe('display_name')
        ->and(RaxosTests\Database\UnitSecretView::col('name')->column)->toBe('secret')
        ->and(UnitAccount::alias('name', 'shared')->column)->toBe('display_name')
        ->and(RaxosTests\Database\UnitSecretView::alias('name', 'shared')->column)->toBe('secret');
});


it('finds requested primary keys in caller order with warm and cold caches', function (): void {
    UnitAccount::singleOrFail(1);
    $models = UnitAccount::find([2, 99, 1, 2]);
    expect(array_map(static fn (UnitAccount $model): int => $model->id, $models->toArray()))->toBe([2, 1, 2])
        ->and(UnitAccount::find([])->isEmpty())->toBeTrue()->and(UnitAccount::exists(1))->toBeTrue()->and(UnitAccount::exists(99))->toBeFalse()
        ->and(UnitAccount::single(99))->toBeNull();
    expect(fn () => UnitAccount::singleOrFail(99))->toThrow(Raxos\Database\Orm\Error\NotFoundException::class);
});

it('exposes model table and aliased column references without dropping query filters', function (): void {
    expect(UnitAccount::table())->toBe('unit_accounts')->and(UnitAccount::alias('name', 'a')->column)->toBe('display_name')
        ->and(UnitAccount::alias('*', 'a')->table)->toBe('a')->and(UnitAccount::col('*')->column)->toBe('*');
    expect(UnitAccount::where(UnitAccount::col('id'), 2)->single()->id)->toBe(2)
        ->and(UnitAccount::whereIn(UnitAccount::col('id'), [1, 2])->resultCount())->toBe(2)
        ->and(UnitAccount::whereNotIn(UnitAccount::col('id'), [1])->resultCount())->toBe(1)
        ->and(UnitAccount::whereNull(UnitAccount::col('state'))->resultCount())->toBe(1)
        ->and(UnitAccount::whereNotNull(UnitAccount::col('state'))->resultCount())->toBe(1)
        ->and(UnitAccount::all(1, 1)->first()->id)->toBe(2);
});
