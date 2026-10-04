<?php
declare(strict_types=1);

use Raxos\Database\Orm\Error\{InvalidColumnException, MissingPolymorphicDiscriminatorException, MissingPropertyException};
use Raxos\Database\Orm\Structure\{Structure, StructureGenerator};
use RaxosTests\Database\{ParentModel, UnitAccount, UnitAnimal, UnitCat};
use function RaxosTests\Database\unitAccounts;

covers(Structure::class);

beforeEach(function (): void {
    unitAccounts();
});

it('resolves names and aliases to properties and typed column references', function (): void {
    $structure = StructureGenerator::for(UnitAccount::class);
    expect($structure->hasProperty('name'))->toBeTrue()->and($structure->hasProperty('label'))->toBeTrue()->and($structure->hasProperty('missing'))->toBeFalse()
        ->and($structure->getColumn('label')->column)->toBe('display_name')->and($structure->getColumn('name', 'a')->table)->toBe('a')
        ->and($structure->getPrimaryKey())->toHaveCount(1)->and($structure->getRelationPrimaryKey()->column)->toBe('id');
    expect(fn() => $structure->getProperty('missing'))->toThrow(MissingPropertyException::class);
    expect(fn() => $structure->getColumn('display'))->toThrow(InvalidColumnException::class);
});

it('hydrates polymorphic subclasses using the discriminator and caches the correct type', function (): void {
    $model = UnitAnimal::singleOrFail(1);
    expect($model)->toBeInstanceOf(UnitCat::class)->and($model->name)->toBe('Milo')->and($model->kind)->toBe('cat');
    $structure = StructureGenerator::for(UnitAnimal::class);
    expect(fn() => $structure->createInstance(['id' => 2]))->toThrow(MissingPolymorphicDiscriminatorException::class);
});

it('resolves relation definitions once and retains the connection after serialization', function (): void {
    $structure = StructureGenerator::for(ParentModel::class);
    $property = $structure->getProperty('children');
    expect($structure->getRelation($property))->toBe($structure->getRelation($property))->and(iterator_to_array($structure->getRelations()))->toHaveCount(2);
    $restored = unserialize(serialize($structure));
    expect($restored)->toBeInstanceOf(Structure::class)->and($restored->table)->toBe('parents')->and($restored->connection)->toBe($structure->connection)
        ->and($restored->getProperty('id')->isPrimaryKey)->toBeTrue();
});
