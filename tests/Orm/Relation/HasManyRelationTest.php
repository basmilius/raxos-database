<?php
declare(strict_types=1);

use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Relation\HasManyRelation;
use Raxos\Database\Orm\Structure\StructureGenerator;
use RaxosTests\Database\{UnitDepartment};
use function RaxosTests\Database\unitRelations;

covers(HasManyRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});

it('returns sorted matching rows and empty collections equally for lazy and eager reads', function (bool $eager): void {
    $query = UnitDepartment::select()->orderBy('id');
    if ($eager) {
        $query->eagerLoad('teams');
    }
    $models = $query->array();
    expect($models[0]->teams->column('id')->toArray())->toBe([11, 10])
        ->and($models[1]->teams->isEmpty())->toBeTrue()->and($models[2]->teams->column('id')->toArray())->toBe([20]);
    $relation = StructureGenerator::for(UnitDepartment::class)->getRelation(StructureGenerator::for(UnitDepartment::class)->getProperty('teams'));
    $relation->eagerLoad(new ModelArrayList($models));
    expect($models[0]->teams->column('id')->toArray())->toBe([11, 10]);
    expect(UnitDepartment::select()->whereHas('teams')->resultCount())->toBe(2);
    expect(UnitDepartment::select()->whereNotHas('teams')->resultCount())->toBe(1);
})->with([false, true]);
