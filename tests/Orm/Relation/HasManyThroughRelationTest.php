<?php
declare(strict_types=1);

use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Relation\HasManyThroughRelation;
use Raxos\Database\Orm\Structure\StructureGenerator;
use RaxosTests\Database\{UnitDepartment, UnitMember};
use function RaxosTests\Database\unitRelations;

covers(HasManyThroughRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});


it('loads ordered members through teams and reuses the correct per-parent grouping', function (bool $eager): void {
    UnitMember::singleOrFail(100);
    $query = UnitDepartment::select()->orderBy('id');
    if ($eager) {
        $query->eagerLoad('members');
    }
    $models = $query->array();
    if ($eager) {
        foreach ($models as $model) {
            expect($model->backbone->relationCache->hasValue('members'))->toBeTrue();
        }
    }
    expect($models[0]->members->column('id')->toArray())->toBe([102, 101, 100])->and($models[1]->members->isEmpty())->toBeTrue()
        ->and($models[2]->members->column('id')->toArray())->toBe([200]);
    expect(UnitDepartment::select()->whereHas('members')->resultCount())->toBe(2);
    $relation = StructureGenerator::for(UnitDepartment::class)->getRelation(StructureGenerator::for(UnitDepartment::class)->getProperty('members'));
    $relation->eagerLoad(new ModelArrayList($models));
    expect($models[0]->members->column('id')->toArray())->toBe([102, 101, 100]);
})->with([false, true]);

it('caches an empty through relation without issuing a lazy query', function (): void {
    $department = UnitDepartment::select()->wherePrimaryKey(UnitDepartment::class, 2)->eagerLoad('members')->singleOrFail();
    expect($department->backbone->relationCache->hasValue('members'))->toBeTrue();
    $this->connection->logger->enable();
    $before = $this->connection->logger->count();
    expect($department->members->isEmpty())->toBeTrue()
        ->and($this->connection->logger->count())->toBe($before);
    $this->connection->logger->disable();
});
