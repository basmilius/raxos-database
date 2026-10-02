<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Logger\QueryEvent;
use Raxos\Database\Orm\Cache;
use Raxos\Database\Orm\Caster\JsonCaster;
use Raxos\Error\InvalidArgumentException;
use RaxosTests\Database\ChildModel;
use RaxosTests\Database\ParentModel;
use RaxosTests\Database\SoftModel;

beforeEach(function (): void {
    $this->connection = new SQLite('sqlite::memory:');
    Db::register($this->connection);
    if (!$this->connection->connected) {
        $this->connection->connect();
    }
    $this->connection->cache->flushAll();
    $this->connection->pdo->exec('DROP TABLE IF EXISTS parents; DROP TABLE IF EXISTS children; DROP TABLE IF EXISTS soft_items');
    $this->connection->pdo->exec('CREATE TABLE parents (id INTEGER PRIMARY KEY, external_key INTEGER, name TEXT)');
    $this->connection->pdo->exec('CREATE TABLE children (id INTEGER PRIMARY KEY, parent_id INTEGER, parent_key INTEGER)');
    $this->connection->pdo->exec('CREATE TABLE soft_items (id INTEGER PRIMARY KEY, group_id INTEGER, deleted_at TEXT)');
    $this->connection->pdo->exec("INSERT INTO parents VALUES (1,100,'first'),(100,999,'other')");
    $this->connection->pdo->exec('INSERT INTO children VALUES (1,1,100)');
    $this->connection->pdo->exec("INSERT INTO soft_items VALUES (1,1,NULL),(2,2,'2026-01-01'),(3,2,NULL)");
});

it('evicts without reindexing numeric primary keys and normalizes composite keys', function (): void {
    $cache = new Cache(2);
    $first = ParentModel::where(ParentModel::col('id'), 1)->single();
    $second = ParentModel::where(ParentModel::col('id'), 100)->single();
    $cache->set(ParentModel::class, 10, $first);
    $cache->set(ParentModel::class, 20, $second);
    $cache->set(ParentModel::class, 30, $first);
    expect($cache->get(ParentModel::class, 10))->toBeNull();
    expect($cache->get(ParentModel::class, 20))->toBe($second);
    expect($cache->get(ParentModel::class, 30))->toBe($first);
    expect($cache->get(ParentModel::class, 0))->toBeNull();
    $cache->set(ParentModel::class, ['tenant' => 'a', 'id' => 1], $first);
    expect($cache->get(ParentModel::class, ['id' => 1, 'tenant' => 'a']))->toBe($first);
});

it('groups OR predicates under the soft delete condition without mutating the query', function (): void {
    $query = SoftModel::select()->where(SoftModel::col('id'), 1)->orWhere(SoftModel::col('id'), 2)->orderBy(SoftModel::col('id'))->limit(2);
    $sql = $query->toSql();
    expect(array_map(static fn(SoftModel $model) => $model->id, $query->array()))->toBe([1]);
    expect($query->toSql())->toBe($sql);
    expect(array_map(static fn(SoftModel $model) => $model->id, $query->array()))->toBe([1]);
    expect(array_map(static fn(SoftModel $model) => $model->id, $query->withDeleted()->array()))->toBe([1,2]);
});

it('adds soft delete filtering before grouping and pagination when no WHERE exists', function (): void {
    expect(count(SoftModel::select()->orderBy(SoftModel::col('id'))->limit(10)->array()))->toBe(2);
    expect(SoftModel::select()->totalCount())->toBe(2);
});

it('resolves custom relation keys correctly with cold and unrelated warm caches', function (bool $warm): void {
    if ($warm) {
        ParentModel::where(ParentModel::col('id'), 100)->single();
    }
    $child = ChildModel::select()->eagerLoad('parent')->single();
    expect($child->parent->id)->toBe(1);
    $this->connection->cache->flushAll();
    if ($warm) {
        ChildModel::where(ChildModel::col('id'), 1)->single();
    }
    expect(ParentModel::where(ParentModel::col('id'), 100)->eagerLoad('child')->single()->child)->toBeNull();
})->with([false, true]);

it('eager loads cursor relations once per batch and releases streamed identities', function (): void {
    $this->connection->pdo->exec('DELETE FROM parents; DELETE FROM children');
    $insertParent = $this->connection->pdo->prepare('INSERT INTO parents VALUES (?,?,?)');
    $insertChild = $this->connection->pdo->prepare('INSERT INTO children VALUES (?,?,?)');
    for ($i = 1; $i <= 250; $i++) {
        $insertParent->execute([$i, $i, 'parent']);
        $insertChild->execute([$i, $i, $i]);
    }
    ParentModel::select()->limit(1)->array();
    $this->connection->cache->flushAll();
    $this->connection->logger->enable();
    $before = count(array_filter(new ReflectionProperty($this->connection->logger, 'events')->getValue($this->connection->logger), static fn(Raxos\Database\Logger\Event $event) => $event instanceof QueryEvent));
    $seen = [];
    foreach (ParentModel::select()->orderBy(ParentModel::col('id'))->eagerLoad('children')->cursor(batchSize: 100) as $parent) {
        $seen[] = $parent->id;
        expect($parent->children->first()->parent_id)->toBe($parent->id);
    }
    $after = count(array_filter(new ReflectionProperty($this->connection->logger, 'events')->getValue($this->connection->logger), static fn(Raxos\Database\Logger\Event $event) => $event instanceof QueryEvent));
    expect($seen)->toBe(range(1,250));
    expect($after - $before)->toBe(4);
    expect($this->connection->cache->has(ParentModel::class, 1))->toBeFalse();
    expect($this->connection->cache->has(ChildModel::class, 250))->toBeFalse();
});

it('preserves pre-existing modified identities while streaming and supports retaining identities explicitly', function (): void {
    $existing = ParentModel::where(ParentModel::col('id'), 1)->single();
    $existing->name = 'modified';
    $stream = iterator_to_array(ParentModel::select()->orderBy(ParentModel::col('id'))->cursor(batchSize: 1));
    expect($stream[0]->backbone)->toBe($existing->backbone);
    expect($stream[0]->name)->toBe('modified');
    expect($this->connection->cache->get(ParentModel::class, 1))->toBe($existing);
    expect($this->connection->cache->has(ParentModel::class, 100))->toBeFalse();
    iterator_to_array(ParentModel::select()->cursor(retainCache: true));
    expect($this->connection->cache->has(ParentModel::class, 100))->toBeTrue();
});

it('validates cursor batch sizes', function (): void {
    expect(fn() => iterator_to_array(ParentModel::select()->cursor(batchSize: 0)))->toThrow(InvalidArgumentException::class);
});

it('cleans hydration scopes when a callback fails', function (): void {
    $existing = ParentModel::where(ParentModel::col('id'), 1)->single();
    expect(function (): void {
        $this->connection->cache->scope(function (): void {
            ParentModel::where(ParentModel::col('id'), 100)->single();
            throw new RuntimeException('hydrate failed');
        });
    })->toThrow(RuntimeException::class);
    expect($this->connection->cache->get(ParentModel::class, 1))->toBe($existing);
    expect($this->connection->cache->has(ParentModel::class, 100))->toBeFalse();
});

it('round trips stored JSON and preserves invalid-storage behavior', function (): void {
    $model = ParentModel::where(ParentModel::col('id'), 1)->single();
    $caster = new JsonCaster();
    $data = ['enabled' => true, 'count' => 0, 'label' => 'héllo'];
    expect($caster->decode($caster->encode($data, $model), $model))->toBe($data);
    expect($caster->decode('42', $model))->toBe(42);
    expect($caster->decode(null, $model))->toBeNull();
    expect($caster->decode('{bad', $model))->toBeNull();
});

it('does not confuse a cached child primary key with its reference column', function (bool $warm): void {
    $this->connection->pdo->exec('DELETE FROM children; INSERT INTO children VALUES (1,100,999),(2,1,100)');
    if ($warm) {
        ChildModel::where(ChildModel::col('id'), 1)->single();
    }
    expect(ParentModel::where(ParentModel::col('id'), 1)->eagerLoad('child')->single()->child->id)->toBe(2);
})->with([false, true]);

it('retains existing identities when a bounded cache hydrates a temporary batch', function (): void {
    $cache = new Cache(1);
    $existing = ParentModel::where(ParentModel::col('id'), 1)->single();
    $cache->set(ParentModel::class, 1, $existing);
    $cache->scope(function () use ($cache, $existing): void {
        $cache->set(ParentModel::class, 2, $existing);
        expect($cache->get(ParentModel::class, 1))->toBe($existing);
    });
    expect($cache->get(ParentModel::class, 1))->toBe($existing);
    expect($cache->has(ParentModel::class, 2))->toBeFalse();
});

it('persists new models and dirty updates and invalidates deleted identities', function (): void {
    $model = new ParentModel();
    $model->external_key = 123;
    $model->name = 'created';
    $model->save();
    expect($model->id)->toBeGreaterThan(0)
        ->and($this->connection->column("SELECT name FROM parents WHERE id = {$model->id}"))->toBe('created');
    $model->name = 'updated';
    expect($model->backbone->isModified())->toBeTrue();
    $model->save();
    expect($model->backbone->isModified())->toBeFalse()
        ->and($this->connection->column("SELECT name FROM parents WHERE id = {$model->id}"))->toBe('updated');
    $id = $model->id;
    $model->destroy();
    expect($this->connection->column("SELECT name FROM parents WHERE id = {$id}"))->toBeFalse()
        ->and($this->connection->cache->has(ParentModel::class, $id))->toBeFalse();
});

it('marks the outer transaction rollback-only after an inner rollback', function (): void {
    $connection = $this->connection;
    $connection->transaction();
    $connection->execute("INSERT INTO parents VALUES (200,200,'outer')");
    $connection->transaction();
    $connection->execute("INSERT INTO parents VALUES (201,201,'inner')");
    $connection->rollBack();
    expect(fn(): bool => $connection->commit())->toThrow(Raxos\Database\Query\Error\RollbackOnlyTransactionException::class);
    expect($connection->column('SELECT name FROM parents WHERE id = 200'))->toBeFalse()
        ->and($connection->column('SELECT name FROM parents WHERE id = 201'))->toBeFalse();
});

it('closes a partially consumed cursor and leaves the connection reusable', function (): void {
    $cursor = ParentModel::select()->cursor(batchSize: 1);
    foreach ($cursor as $model) {
        expect($model)->toBeInstanceOf(ParentModel::class);
        break;
    }
    unset($cursor);
    expect($this->connection->column('SELECT count(*) FROM parents'))->toBe(2)
        ->and($this->connection->cache->find(ParentModel::class, static fn(ParentModel $model): bool => true))->toBeNull();
});
