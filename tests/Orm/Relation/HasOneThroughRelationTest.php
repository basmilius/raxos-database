<?php
declare(strict_types=1);

use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Relation\HasOneThroughRelation;
use Raxos\Database\Orm\Structure\StructureGenerator;
use RaxosTests\Database\{UnitDepartment};
use function RaxosTests\Database\unitRelations;

covers(HasOneThroughRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});

it('loads a single related model through the linking model and handles missing links', function (bool $eager): void {
    $query = UnitDepartment::select()->whereIn(UnitDepartment::col('id'), [2, 3])->orderBy('id');
    if ($eager) {
        $query->eagerLoad('leader');
    }
    $models = $query->array();
    expect($models[0]->leader)->toBeNull()->and($models[1]->leader->id)->toBe(200);
    expect(UnitDepartment::select()->whereHas('leader')->resultCount())->toBe(2);
    $relation = StructureGenerator::for(UnitDepartment::class)->getRelation(StructureGenerator::for(UnitDepartment::class)->getProperty('leader'));
    $relation->eagerLoad(new ModelArrayList($models));
    expect($models[1]->leader->id)->toBe(200);
})->with([false, true]);
