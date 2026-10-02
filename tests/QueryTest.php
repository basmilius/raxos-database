<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Query\Literal\Literal;
use RaxosTests\Database\SoftModel;
use function Raxos\Database\Query\column;

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

it('preserves implicit soft-delete filters when wrapping counts', function (): void {
    $this->connection->pdo->exec('CREATE TABLE soft_items (id INTEGER PRIMARY KEY, group_id INTEGER, deleted_at TEXT)');
    $this->connection->pdo->exec("INSERT INTO soft_items VALUES (1,1,NULL),(2,1,NULL),(3,2,'2026-01-01')");
    $query = SoftModel::select()->where(SoftModel::col('group_id'), 1)->orWhere(SoftModel::col('group_id'), 2)->limit(1);
    $sql = $query->toSql();
    expect($query->resultCount())->toBe(1)
        ->and($query->totalCount())->toBe(2)
        ->and($query->toSql())->toBe($sql)
        ->and(SoftModel::select()->withDeleted()->totalCount())->toBe(3);
});

it('inserts late WHERE and OR predicates before grouping, HAVING and pagination', function (): void {
    $query = Db::query()->select('group_id', Literal::of('count(*) as members'))->from('items')
        ->groupBy('group_id')->having(Literal::of('members > 0'))->orderBy('group_id')->limit(1)
        ->where(column('group_id'), 2)->orWhere(column('group_id'), 3);
    expect($query->array())->toBe([['group_id' => 2, 'members' => 1]])
        ->and($query->resultCount())->toBe(1)
        ->and($query->totalCount())->toBe(2);
});

it('inserts correlated subqueries and their bindings before an existing ORDER BY', function (): void {
    $subquery = Db::query()->select(1)->from('items', 'lookup')
        ->where(column('id', 'lookup'), column('id', 'items'))->where(column('group_id', 'lookup'), 2);
    $query = Db::query()->select('id')->from('items')->orderBy('id')->limit(2)->whereExists($subquery);
    expect($query->array())->toBe([['id' => 3]])->and($query->totalCount())->toBe(1);
});
