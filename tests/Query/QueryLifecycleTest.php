<?php
declare(strict_types=1);

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\Orm\{PrimerInterface, PrimerTiming};
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Query;
use RaxosTests\Database\{UnitDepartment, UnitTeam};
use function RaxosTests\Database\unitRelations;

covers(Query::class);

it('runs callable and interface primers around eager relations once per hydrated batch', function (): void {
    $connection = unitRelations();
    $trace = [];
    $before = static function (ArrayListInterface $models, ConnectionInterface $received) use (&$trace, $connection): void {
        expect($received)->toBe($connection)->and($models)->toBeInstanceOf(ModelArrayList::class);
        foreach ($models as $model) {
            expect($model->backbone->relationCache->hasValue('teams'))->toBeFalse();
        }
        $trace[] = ['before', $models->count()];
    };
    $after = new class($trace) implements PrimerInterface
    {
        public function __construct(public array &$trace)
        {
        }
        public function prime(ArrayListInterface $models, ConnectionInterface $connection): void
        {
            foreach ($models as $model) {
                expect($model->backbone->relationCache->hasValue('teams'))->toBeTrue();
            }
            $this->trace[] = ['after', $models->count()];
        }
    };
    $query = UnitDepartment::select()->orderBy('id')->eagerLoad('teams')->prime($before, PrimerTiming::BeforeRelations)->prime($after);
    expect($query->arrayList())->toHaveCount(3)->and($trace)->toBe([['before', 3], ['after', 3]]);
    $connection->cache->flushAll();
    $trace = [];
    expect(iterator_to_array($query->cursor(batchSize: 2)))->toHaveCount(3)->and($trace)->toBe([['before', 2], ['after', 2], ['before', 1], ['after', 1]]);
});

it('disables or resets eager relations without disabling model hydration', function (): void {
    $connection = unitRelations();
    $query = UnitDepartment::select()->eagerLoad('teams')->eagerLoadDisable('teams');
    $model = $query->singleOrFail();
    expect($query->isModelQuery())->toBeTrue()->and($model->backbone->relationCache->hasValue('teams'))->toBeFalse();
    $connection->cache->flushAll();
    $model = UnitDepartment::select()->eagerLoad('teams')->eagerLoadReset()->singleOrFail();
    expect($model->backbone->relationCache->hasValue('teams'))->toBeFalse();
    expect(UnitDepartment::select()->withoutModel()->isModelQuery())->toBeFalse();
});

it('correlates relation conditions, OR alternatives and primary-key sets', function (): void {
    unitRelations();
    expect(UnitDepartment::select()->whereRelation('teams', UnitTeam::col('id'), 11)->arrayList()->column('id')->toArray())->toBe([1]);
    expect(UnitDepartment::select()->where(UnitDepartment::col('id'), 2)->orWhereRelation('teams', UnitTeam::col('id'), 20)->orderBy('id')->arrayList()->column('id')->toArray())->toBe([2, 3]);
    expect(UnitDepartment::select()->whereNotHas('teams')->arrayList()->column('id')->toArray())->toBe([2]);
    expect(UnitDepartment::select()->wherePrimaryKeyIn(UnitDepartment::class, [1, 3])->orderBy('id')->arrayList()->column('id')->toArray())->toBe([1, 3]);
});

it('preserves zero foreign keys and does not select unrelated rows for null relation keys', function (): void {
    $connection = unitRelations();
    $connection->execute("INSERT INTO unit_departments VALUES (0,'zero')");
    $connection->execute('UPDATE unit_teams SET department_id=0 WHERE id=10');
    expect(UnitTeam::singleOrFail(10)->department->id)->toBe(0);
    $models = UnitTeam::select()->eagerLoad('department')->orderBy('id')->array();
    expect($models[0]->department->id)->toBe(0);
    $new = new UnitDepartment();
    expect($new->teams->isEmpty())->toBeTrue()->and($new->members->isEmpty())->toBeTrue()->and($new->leader)->toBeNull();
});
