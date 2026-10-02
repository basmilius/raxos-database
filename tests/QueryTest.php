<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Query\Literal\Literal;

beforeEach(function (): void {
    $this->connection = new SQLite('sqlite::memory:');
    Db::register($this->connection);
    $this->connection->connect();
    $this->connection->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, group_id INTEGER)');
    $this->connection->pdo->exec('INSERT INTO items VALUES (1,1),(2,1),(3,2),(4,3)');
});

it('keeps zero limits and counts the paginated result separately from the total', function (): void {
    $query = Db::query()->select()->from('items')->limit(0);
    expect($query->toSql())->toContain('limit 0');
    expect($query->array())->toBe([]);
    expect($query->resultCount())->toBe(0);
    expect($query->totalCount())->toBe(4);
});

it('counts grouped and distinct result rows including HAVING aliases', function (): void {
    $query = Db::query()->select('group_id')->from('items')->groupBy('group_id')->limit(2);
    expect($query->resultCount())->toBe(2);
    expect($query->totalCount())->toBe(3);
    $distinct = Db::query()->selectDistinct('group_id')->from('items');
    expect($distinct->totalCount())->toBe(3);
    $having = Db::query()->select('group_id', Literal::of('count(*) as members'))->from('items')->groupBy('group_id')->having(Literal::of('members > 1'));
    expect($having->totalCount())->toBe(1);
});

it('returns every PDO scalar shape and the no-row sentinel', function (): void {
    expect(Db::column('SELECT 1.5'))->toBe(1.5);
    expect(Db::column('SELECT NULL'))->toBeNull();
    expect(Db::column('SELECT 0'))->toBe(0);
    expect(Db::column("SELECT 'text'"))->toBe('text');
    expect(Db::column('SELECT id FROM items WHERE id = -1'))->toBeFalse();
});
