<?php
declare(strict_types=1);

use Raxos\Database\Orm\Relation\BelongsToRelation;
use RaxosTests\Database\{UnitDepartment, UnitTeam};
use function RaxosTests\Database\unitRelations;

covers(BelongsToRelation::class);

beforeEach(function (): void {
    $this->connection = unitRelations();
});

it('uses cached primary-key identities and returns null for missing references', function (bool $eager, bool $warm): void {
    if ($warm) {
        UnitDepartment::singleOrFail(1);
    }
    $query = UnitTeam::select()->orderBy('id');
    if ($eager) {
        $query->eagerLoad('department');
    }
    $models = $query->array();
    expect($models[0]->department->id)->toBe(1)->and($models[1]->department->backbone)->toBe($models[0]->department->backbone)->and($models[2]->department->id)->toBe(3);
    $models[0]->department = null;
    $models[0]->save();
    expect($models[0]->department)->toBeNull()->and($this->connection->column('SELECT department_id FROM unit_teams WHERE id=10'))->toBeNull();
    expect(UnitTeam::select()->whereHas('department')->resultCount())->toBe(2);
})->with([[false, false], [true, false], [false, true], [true, true]]);
