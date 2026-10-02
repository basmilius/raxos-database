<?php
declare(strict_types=1);

use Raxos\Database\Orm\Structure\{StructureGenerator, StructureHelper};
use RaxosTests\Database\UnitAccount;
use function RaxosTests\Database\unitAccounts;

covers(StructureHelper::class);

beforeEach(function (): void {
    unitAccounts();
});

it('normalizes aliases and nested visibility maps at the appropriate model level', function (): void {
    $structure = StructureGenerator::for(UnitAccount::class);
    expect(StructureHelper::normalizeKeys('label', $structure))->toBe(['name' => null])
        ->and(StructureHelper::normalizeKeys(['label', 'address' => ['city', 'postal']], $structure))->toBe(['name' => null, 'address' => ['city' => null, 'postal' => null]])
        ->and(StructureHelper::normalizeKeys(['name' => null]))->toBe(['name' => null]);
});

it('combines declared hidden or visible metadata with explicit visibility overrides', function (): void {
    $structure = StructureGenerator::for(UnitAccount::class);
    expect(StructureHelper::isVisible($structure->getProperty('secret'), false, false))->toBeFalse()
        ->and(StructureHelper::isVisible($structure->getProperty('secret'), true, false))->toBeTrue()
        ->and(StructureHelper::isVisible($structure->getProperty('name'), false, true))->toBeFalse()
        ->and(StructureHelper::isVisible($structure->getProperty('display'), false, false))->toBeTrue();
    expect(StructureHelper::groupInstances(UnitAccount::find([1, 2]))->toArray())->toHaveKey(UnitAccount::class);
});
