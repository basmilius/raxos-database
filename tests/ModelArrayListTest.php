<?php
declare(strict_types=1);

use Raxos\Collection\ArrayList;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Orm\Error\ReadonlyModelException;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\ReadonlyModel;
use RaxosTests\Database\ParentModel;
use RaxosTests\Database\SpecializedModelArrayList;

beforeEach(function (): void {
    $connection = new SQLite('sqlite::memory:');
    Db::register($connection);
    $connection->connect();
    $connection->pdo->exec('CREATE TABLE parents (id INTEGER PRIMARY KEY, external_key INTEGER, name TEXT)');
    $connection->pdo->exec('CREATE TABLE children (id INTEGER PRIMARY KEY, parent_id INTEGER, parent_key INTEGER)');
    $connection->pdo->exec("INSERT INTO parents VALUES (1,100,'first'),(2,200,'second')");
    $connection->pdo->exec('INSERT INTO children VALUES (1,1,100),(2,2,200)');
});

it('preserves the concrete collection type for empty model views', function (string $operation): void {
    $models = new SpecializedModelArrayList();
    $result = $operation === 'readonly' ? $models->readonly() : $models->{$operation}('name');
    expect($result)->toBeInstanceOf(SpecializedModelArrayList::class)
        ->not->toBe($models)
        ->and($result->toArray())->toBe([]);
})->with(['readonly', 'makeHidden', 'makeVisible', 'only']);

it('preserves collection keys and shared model identities when creating visibility views', function (string $operation): void {
    $parents = ParentModel::select()->orderBy(ParentModel::col('id'))->arrayList();
    $models = new SpecializedModelArrayList(['first' => $parents[0], 7 => $parents[1]]);
    $before = json_decode(json_encode($models, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $result = $models->{$operation}('name');
    expect($result)->toBeInstanceOf(SpecializedModelArrayList::class)
        ->not->toBe($models)
        ->and(array_keys($result->toArray()))->toBe(['first', 7])
        ->and($result['first']->backbone)->toBe($models['first']->backbone)
        ->and(json_decode(json_encode($models, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))->toBe($before);
    $data = $result['first']->toArray();
    if ($operation === 'makeHidden') {
        expect($data)->not->toHaveKey('name');
    } elseif ($operation === 'only') {
        expect($data)->toBe(['name' => 'first']);
    } else {
        expect($data['name'])->toBe('first');
    }
})->with(['makeHidden', 'makeVisible', 'only']);

it('keeps readonly model collections and nested relations backed by the original identities', function (): void {
    $models = ParentModel::select()->eagerLoad('children')->orderBy(ParentModel::col('id'))->arrayList();
    $views = $models->readonly();
    expect($views)->toBeInstanceOf(ModelArrayList::class)
        ->and($views[0])->toBeInstanceOf(ReadonlyModel::class)
        ->and($views[0]->backbone)->toBe($models[0]->backbone)
        ->and($views[0]->children)->toBeInstanceOf(ModelArrayList::class)
        ->and($views[0]->children[0])->toBeInstanceOf(ReadonlyModel::class);
    expect(fn(): mixed => $views[0]->setValue('name', 'blocked'))->toThrow(ReadonlyModelException::class);
    expect(fn(): mixed => $views[0]->children[0]->setValue('parent_id', 2))->toThrow(ReadonlyModelException::class);
    $models[0]->name = 'updated';
    expect($views[0]->name)->toBe('updated');
});

it('serializes a paginated model list after the Passly visibility callback', function (): void {
    $page = ParentModel::select()->orderBy(ParentModel::col('id'))->paginate(0, 25, static fn(QueryInterface $query, int $offset, int $limit): ModelArrayList => $query
        ->limit($limit, $offset)
        ->eagerLoad('children')
        ->arrayList()
        ->makeVisible('children'));
    $data = json_decode(json_encode($page, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    expect($page->items)->toBeInstanceOf(ModelArrayList::class)
        ->and($data['items'])->toHaveCount(2)
        ->and($data['items'][0]['children'][0]['parent_id'])->toBe(1)
        ->and($data['page'])->toBe(1)
        ->and($data['page_size'])->toBe(25)
        ->and($data['total'])->toBe(2);
});

it('allows a model list to project scalar values into a generic list', function (): void {
    $models = ParentModel::select()->orderBy(ParentModel::col('id'))->arrayList();
    $names = $models->map(static fn(ParentModel $model): string => $model->name);
    expect($names)->toBeInstanceOf(ArrayList::class)
        ->not->toBeInstanceOf(ModelArrayList::class)
        ->and($names->toArray())->toBe(['first', 'second']);
});
