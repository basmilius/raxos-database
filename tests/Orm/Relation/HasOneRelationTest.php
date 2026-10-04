<?php
declare(strict_types=1);

use Raxos\Database\Orm\Relation\HasOneRelation;
use RaxosTests\Database\{UnitDepartment, UnitTeam};
use function RaxosTests\Database\unitRelations;

covers(HasOneRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});

it('selects the first ordered match for lazy, eager and partially cached relations', function (bool $eager, bool $warm): void {
    if ($warm) {
        UnitTeam::singleOrFail(10);
    }
    $query = UnitDepartment::select()->orderBy('id');
    if ($eager) {
        $query->eagerLoad('firstTeam');
    }
    $models = $query->array();
    expect($models[0]->firstTeam?->id)->toBe(11)->and($models[1]->firstTeam)->toBeNull()->and($models[2]->firstTeam?->id)->toBe(20);
    expect(UnitDepartment::select()->whereHas('firstTeam')->resultCount())->toBe(2);
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('persists relation-only save tasks when no parent column was modified', function (): void {
    $department = UnitDepartment::singleOrFail(3);
    $department->firstTeam = null;
    $department->save();
    expect($this->connection->column('SELECT department_id FROM unit_teams WHERE id=20'))->toBeNull();
    $department->firstTeam = UnitTeam::singleOrFail(10);
    $department->save();
    expect($this->connection->column('SELECT department_id FROM unit_teams WHERE id=10'))->toBe(3);
});
