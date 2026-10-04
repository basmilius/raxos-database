<?php
declare(strict_types=1);

use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Relation\BelongsToThroughRelation;
use Raxos\Database\Orm\Structure\StructureGenerator;
use RaxosTests\Database\{UnitMember};
use function RaxosTests\Database\unitRelations;

covers(BelongsToThroughRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});

it('resolves the shared parent through another model without leaking identities between rows', function (bool $eager): void {
    $query = UnitMember::select()->orderBy('id');
    if ($eager) {
        $query->eagerLoad('department');
    }
    $models = $query->array();
    expect($models[0]->department->id)->toBe(1)->and($models[1]->department->id)->toBe(1)
        ->and($models[3]->department->id)->toBe(3)->and($models[4]->department)->toBeNull();
    expect(UnitMember::select()->whereHas('department')->resultCount())->toBe(4);
    $relation = StructureGenerator::for(UnitMember::class)->getRelation(StructureGenerator::for(UnitMember::class)->getProperty('department'));
    $relation->eagerLoad(new ModelArrayList($models));
    expect($models[4]->department)->toBeNull();
})->with([false, true]);
