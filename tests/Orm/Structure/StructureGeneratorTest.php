<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Orm\Structure\StructureGenerator;
use RaxosTests\Database\{UnitAccount, UnitInvalidCaster, UnitInvalidEmbedded, UnitMissingTable, UnitNamedBase, UnitNamedChild};
use function RaxosTests\Database\unitAccounts;

covers(StructureGenerator::class);

beforeEach(function (): void {
    unitAccounts();
});

it('generates and caches complete column, macro and embedded metadata', function (): void {
    $structure = StructureGenerator::for(UnitAccount::class);
    expect(StructureGenerator::for(UnitAccount::class))->toBe($structure)->and($structure->table)->toBe('unit_accounts')
        ->and($structure->getProperty('label')->name)->toBe('name')->and($structure->getProperty('name')->key)->toBe('display_name')
        ->and($structure->getProperty('enabled')->caster)->toBe(Raxos\Database\Orm\Caster\BooleanCaster::class)
        ->and($structure->getProperty('address')->columns[0]->key)->toBe('home_city')
        ->and($structure->getProperty('secret')->isHidden)->toBeTrue()->and($structure->getProperty('doubled')->isComputed)->toBeTrue();
    StructureGenerator::clear();
    expect(StructureGenerator::for(UnitAccount::class))->not->toBe($structure);
    StructureGenerator::define($structure);
    expect(StructureGenerator::for(UnitAccount::class))->toBe($structure);
});

it('rejects classes with invalid model, table, caster or embedded definitions', function (string $class, string $exception): void {
    try {
        StructureGenerator::for($class);
    } catch (Raxos\Database\Orm\Error\ConnectionFailedException $error) {
        expect($error->getPrevious())->toBeInstanceOf($exception);
        return;
    } catch (Raxos\Database\Orm\Error\InvalidModelException $error) {
        expect($error)->toBeInstanceOf($exception);
        return;
    }
    test()->fail('Expected a definition error.');
})->with([
    [stdClass::class, Raxos\Database\Orm\Error\InvalidModelException::class],
    [UnitMissingTable::class, Raxos\Database\Orm\Error\MissingTableException::class],
    [UnitInvalidCaster::class, Raxos\Database\Orm\Error\InvalidCasterException::class],
    [UnitInvalidEmbedded::class, Raxos\Database\Orm\Error\InvalidEmbeddableException::class],
]);

it('inherits the parent connection ID instead of falling back to the default connection', function (): void {
    $named = SQLite::createFromInMemory();
    Db::register($named, 'unit-secondary');
    try {
        $parent = StructureGenerator::for(UnitNamedBase::class);
        $child = StructureGenerator::for(UnitNamedChild::class);
        expect($child->connectionId)->toBe('unit-secondary')->and($child->connection)->toBe($named)->and($child->parent)->toBe($parent);
    } finally {
        Db::unregister('unit-secondary');
        StructureGenerator::clear();
    }
});
