<?php
declare(strict_types=1);

use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Relation\BelongsToManyRelation;
use Raxos\Database\Orm\Structure\StructureGenerator;
use RaxosTests\Database\{UnitMember};
use function RaxosTests\Database\unitRelations;

covers(BelongsToManyRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});


it('preserves zero reference keys and declared ordering equally for lazy and eager reads', function (bool $eager): void {
    $query = UnitMember::select()->whereIn(UnitMember::col('id'), [100, 101, 300])->orderBy('id');
    if ($eager) {
        $query->eagerLoad('tags');
    }
    $models = $query->array();
    expect($models[0]->tags->column('id')->toArray())->toBe([0, 2])->and($models[1]->tags->column('id')->toArray())->toBe([2])
        ->and($models[2]->tags->isEmpty())->toBeTrue();
    expect(UnitMember::select()->whereHas('tags')->resultCount())->toBe(2);
    $relation = StructureGenerator::for(UnitMember::class)->getRelation(StructureGenerator::for(UnitMember::class)->getProperty('tags'));
    $relation->eagerLoad(new ModelArrayList($models));
    expect($models[0]->tags->column('id')->toArray())->toBe([0, 2]);
})->with([false, true]);
