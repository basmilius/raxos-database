<?php
declare(strict_types=1);

use Raxos\Database\Orm\Cache;
use RaxosTests\Database\{UnitAccount};
use function RaxosTests\Database\unitAccounts;

covers(Cache::class);

beforeEach(function (): void {
    $this->connection = unitAccounts();
});

it('normalizes single and composite primary keys and retains identity when replacing entries', function (): void {
    $cache = new Cache(2);
    $first = UnitAccount::singleOrFail(1);
    $second = UnitAccount::singleOrFail(2);
    $cache->set(UnitAccount::class, 1, $first);
    $cache->set(UnitAccount::class, ['id' => '1'], $first);
    expect($cache->get(UnitAccount::class, '1'))->toBe($first);
    $cache->set(UnitAccount::class, ['tenant' => 'a', 'id' => 2], $second);
    expect($cache->get(UnitAccount::class, ['id' => 2, 'tenant' => 'a']))->toBe($second)->and($cache->find(UnitAccount::class, static fn(UnitAccount $model): bool => $model->id === 2))->toBe($second);
    $cache->set(UnitAccount::class, 3, $first);
    expect($cache->has(UnitAccount::class, 1))->toBeFalse()->and($cache->has(UnitAccount::class, 3))->toBeTrue();
    $cache->unset(UnitAccount::class, 3);
    $cache->flush(UnitAccount::class);
    expect($cache->find(UnitAccount::class, static fn() => true))->toBeNull();
    $cache->flushAll();
    expect($cache->__debugInfo())->toBe([]);
});

it('cleans nested hydration scopes after failures while preserving preexisting identities', function (): void {
    $cache = new Cache(1);
    $model = UnitAccount::singleOrFail(1);
    $cache->set(UnitAccount::class, 1, $model);
    expect(function () use ($cache, $model): void {
        $cache->scope(function () use ($cache, $model): void {
            $cache->set(UnitAccount::class, 2, $model);
            $cache->scope(function () use ($cache, $model): void {
                $cache->set(UnitAccount::class, 3, $model);
            });
            expect($cache->has(UnitAccount::class, 3))->toBeFalse()->and($cache->has(UnitAccount::class, 2))->toBeTrue();
            throw new RuntimeException('scope failure');
        });
    })->toThrow(RuntimeException::class);
    expect($cache->has(UnitAccount::class, 1))->toBeTrue()->and($cache->has(UnitAccount::class, 2))->toBeFalse();
});
