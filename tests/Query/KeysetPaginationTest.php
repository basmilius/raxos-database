<?php
declare(strict_types=1);

use Pdo\Mysql;
use Raxos\Collection\ArrayList;
use Raxos\Database\Logger\QueryEvent;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Error\InvalidCursorException;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Query;
use RaxosTests\Database\{ChildModel, ParentModel};
use function RaxosTests\Database\{unitConnection, unitOrm};

covers(Query::class);

it('pages compound non-unique sort values without OFFSET or COUNT on every driver', function (string $driver, bool $descending): void {
    $connection = unitConnection($driver);
    $table = 'raxos_unit_keyset';
    $connection->execute('DROP TABLE IF EXISTS ' . $table);

    try {
        $connection->execute('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, position INTEGER, enabled INTEGER)');

        for ($id = 1; $id <= 11; ++$id) {
            $connection->query()->insertIntoValues($table, ['id' => $id, 'position' => intdiv($id - 1, 3), 'enabled' => $id % 2])->run();
        }
        $query = $connection->query()->select()->from($table)->whereField('enabled', 1)->orWhere(new ColumnRef('id'), 2)->orderByDesc('id')->limit(99);
        $original = $query->toSql();
        $connection->logger->enable();
        $ids = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $query->cursorPaginate(size: 2, cursor: $cursor, columns: ['position', 'id'], descending: $descending);
            ++$pages;
            $ids = [...$ids, ...array_column($page->items->toArray(), 'id')];
            $cursor = $page->nextCursor;
            expect($page->hasMore)->toBe($cursor !== null);
        } while ($cursor !== null);
        expect($ids)->toBe($descending ? [11, 9, 7, 5, 3, 2, 1] : [1, 2, 3, 5, 7, 9, 11])->and($query->toSql())->toBe($original)
            ->and($connection->logger->count())->toBe($pages);
        $connection->logger->disable();
    } finally {
        $connection->execute('DROP TABLE IF EXISTS ' . $table);
    }
})->with(['sqlite', 'mysql', 'mariadb'])->with([false, true]);

it('binds cursors to filter values and rejects malformed or nullable keys', function (): void {
    $connection = unitConnection();
    $connection->execute('CREATE TABLE keyset_invalid (id INTEGER PRIMARY KEY, position INTEGER)');
    $connection->execute('INSERT INTO keyset_invalid VALUES (1, 1), (2, 1), (3, NULL)');
    $query = $connection->query()->select()->from('keyset_invalid')->whereField('id', '<', 3);
    $cursor = $query->cursorPaginate(1)->nextCursor;
    expect($cursor)->not->toBeNull();
    expect(fn() => $connection->query()->select()->from('keyset_invalid')->whereField('id', '<', 4)->cursorPaginate(1, $cursor))->toThrow(InvalidCursorException::class);
    expect(fn() => $query->cursorPaginate(1, 'not-a-cursor'))->toThrow(InvalidCursorException::class);
    expect(fn() => $query->cursorPaginate(1, $cursor, descending: true))->toThrow(InvalidCursorException::class);
    expect(fn() => $connection->query()->select()->from('keyset_invalid')->cursorPaginate(columns: ['position', 'id']))->toThrow(InvalidCursorException::class);
    expect(fn() => $query->cursorPaginate(columns: ['id', 'id']))->toThrow(InvalidCursorException::class);
});

it('closes batches before callbacks and allows deletion without skipping stable IDs', function (string $driver): void {
    $connection = unitConnection($driver);
    $table = 'raxos_unit_chunks';
    $connection->execute('DROP TABLE IF EXISTS ' . $table);

    try {
        $connection->execute('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY)');
        $connection->execute('INSERT INTO ' . $table . ' VALUES (1), (2), (3), (4), (5)');
        $seen = [];
        $connection->query()->select()->from($table)->chunkById(function (ArrayList $batch) use ($connection, $table, &$seen): void {
            foreach ($batch as $row) {
                $seen[] = $row['id'];
                $connection->query()->deleteFrom($table)->whereField('id', $row['id'])->run();
            }
        }, batchSize: 2);
        expect($seen)->toBe([1, 2, 3, 4, 5])->and($connection->column('SELECT COUNT(*) FROM ' . $table))->toBe(0);
    } finally {
        $connection->execute('DROP TABLE IF EXISTS ' . $table);
    }
})->with(['sqlite', 'mysql', 'mariadb']);

it('rejects unusable sort columns and bounded cursor payloads', function (array $columns): void {
    $query = unitConnection()->query()->select()->from('unused');
    expect(fn() => $query->cursorPaginate(columns: $columns))->toThrow(InvalidCursorException::class);
})->with([[[]], [[[]]], [[null]], [['id', 'id']], [['id; DROP TABLE users']], [['missing' => 'id']]]);

it('releases ORM identities after early termination and respects compound eager-loading batches', function (): void {
    $connection = unitOrm();

    for ($id = 2; $id <= 9; ++$id) {
        $connection->query()->insertIntoValues('parents', ['id' => $id, 'external_key' => $id, 'name' => 'parent'])->run();
        $connection->query()->insertIntoValues('children', ['id' => $id + 2, 'parent_id' => $id, 'parent_key' => $id])->run();
    }
    $connection->cache->flushAll();
    $connection->logger->enable();
    $ids = [];

    foreach (ParentModel::select()->eagerLoad('children')->lazyById(3) as $parent) {
        $ids[] = $parent->id;
        expect($parent->children->count())->toBeGreaterThan(0);

        if (count($ids) === 4) {
            break;
        }
    }
    unset($parent);
    expect($ids)->toBe([1, 2, 3, 4])->and(new ReflectionProperty($connection->cache, 'size')->getValue($connection->cache))->toBe(0)
        ->and(count(array_filter(new ReflectionProperty($connection->logger, 'events')->getValue($connection->logger), static fn($event): bool => $event instanceof QueryEvent)))->toBe(4);
    $connection->logger->disable();
});

it('stops chunk callbacks explicitly without issuing another page', function (): void {
    $connection = unitConnection();
    $connection->execute('CREATE TABLE keyset_stop (id INTEGER PRIMARY KEY)');
    $connection->execute('INSERT INTO keyset_stop VALUES (1), (2), (3)');
    $connection->logger->enable();
    $ids = [];
    $connection->query()->select()->from('keyset_stop')->chunkById(static function ($rows) use (&$ids): bool {
        $ids = array_column($rows->toArray(), 'id');

        return false;
    }, 1);
    expect($ids)->toBe([1])->and($connection->logger->count())->toBe(1);
    $connection->logger->disable();
});

it('loads eager relationships after closing each unbuffered native result', function (string $driver): void {
    $connection = unitConnection($driver);

    foreach (['children', 'parents'] as $table) {
        $connection->execute('DROP TABLE IF EXISTS ' . $table);
    }
    $connection->execute('CREATE TABLE parents (id INTEGER PRIMARY KEY, external_key INTEGER, name VARCHAR(30))');
    $connection->execute('CREATE TABLE children (id INTEGER PRIMARY KEY, parent_id INTEGER, parent_key INTEGER)');
    $connection->execute("INSERT INTO parents VALUES (1,1,'one'),(2,2,'two'),(3,3,'three')");
    $connection->execute('INSERT INTO children VALUES (1,1,1),(2,2,2),(3,3,3)');
    StructureGenerator::for(ParentModel::class);
    StructureGenerator::for(ChildModel::class);
    $connection->pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, false);

    try {
        $ids = [];

        foreach (ParentModel::select()->eagerLoad('children')->lazyById(2) as $parent) {
            $ids[] = $parent->id;
            expect($parent->children->count())->toBe(1);
        }
        expect($ids)->toBe([1, 2, 3]);
    } finally {
        $connection->pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, true);

        foreach (['children', 'parents'] as $table) {
            $connection->execute('DROP TABLE IF EXISTS ' . $table);
        }
    }
})->with(['mysql', 'mariadb']);
