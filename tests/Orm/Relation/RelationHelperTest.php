<?php
declare(strict_types=1);

use Raxos\Collection\ArrayList;
use Raxos\Database\Orm\Relation\RelationHelper;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use RaxosTests\Database\{UnitTeam};
use function RaxosTests\Database\unitRelations;

covers(RelationHelper::class);

it('composes default and overridden column references without changing the defaults', function (): void {
    $default = new ColumnRef('id', 'people');
    expect(RelationHelper::composeKey(null, null, $default)->column)->toBe('id')
        ->and(RelationHelper::composeKey('custom', 'lookup', $default)->table)->toBe('lookup')->and($default->table)->toBe('people');
});

it('partitions cached primary and custom keys without confusing their lookup columns', function (): void {
    unitRelations();
    $model = UnitTeam::singleOrFail(10);
    $structure = StructureGenerator::for(UnitTeam::class);
    [$cached,$missing] = RelationHelper::partitionModels($structure, new ArrayList([10, 20, null]));
    expect($cached->first())->toBe($model)->and($missing->toArray())->toBe([20]);
    [$cached,$missing] = RelationHelper::partitionModels($structure, new ArrayList([1, 3, null]), UnitTeam::col('department_id'));
    expect($cached->first())->toBe($model)->and($missing->toArray())->toBe([3]);
    expect(RelationHelper::declaringKeyValue($model, UnitTeam::col('department_id')))->toBe(1);
    $model->department_id = null;
    expect(RelationHelper::declaringKeyValue($model, UnitTeam::col('department_id')))->toBeNull();
});
