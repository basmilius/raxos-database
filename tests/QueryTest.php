<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Query\Literal\Literal;
use RaxosTests\Database\SoftModel;
use function Raxos\Database\Query\column;

covers(Raxos\Database\Query\Query::class);

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


it('executes membership, null and negation predicates including empty lists', function (string $method, array $values, array $ids): void {
    $query = Db::query()->select('id')->from('items')->orderBy('id');
    $query->$method(column('group_id'), $values);
    expect(array_column($query->array(), 'id'))->toBe($ids);
})->with([
    ['whereIn', [1, 3], [1, 2, 4]], ['whereNotIn', [1], [3, 4]],
    ['whereIn', [], []], ['whereNotIn', [], [1, 2, 3, 4]],
]);

it('combines OR variants without losing predicate precedence', function (): void {
    $query = Db::query()->select('id')->from('items')->where(column('id'), 99)
        ->orWhereIn(column('group_id'), [1])->orWhereNotIn(column('group_id'), [1, 3])->orderBy('id');
    expect(array_column($query->array(), 'id'))->toBe([1, 2, 3]);
    $this->connection->execute('INSERT INTO items VALUES (5,NULL)');
    expect(Db::query()->select('id')->from('items')->whereNull(column('group_id'))->single())->toBe(['id' => 5]);
    expect(Db::query()->select('id')->from('items')->whereNotNull(column('group_id'))->resultCount())->toBe(4);
    expect(Db::query()->select('id')->from('items')->where(column('id'), 1)->orWhereNull(column('group_id'))->resultCount())->toBe(2);
});

it('places nested predicate groups before existing sorting and pagination', function (): void {
    $query = Db::query()->select('id')->from('items')->orderBy('id')->limit(3)
        ->parenthesis(static function ($query): void {
            $query->where(column('group_id'), 1)->orWhere(column('group_id'), 2);
            $query->parenthesis(static fn ($query) => $query->where(column('id'), '>', 1));
        });
    expect(array_column($query->array(), 'id'))->toBe([1, 2, 3])->and($query->totalCount())->toBe(3);
    $query = Db::query()->select('id')->from('items')->orderBy('id')->limit(3)
        ->parenthesis(static fn ($query) => $query->where(column('group_id'), 1)->orWhere(column('group_id'), 2))
        ->where(column('id'), '>', 1);
    expect(array_column($query->array(), 'id'))->toBe([2, 3]);
});

it('executes inserts, batch values, replacements, dirty sets, returning and deletion', function (): void {
    expect(Db::query()->insertIntoValues('items', [['id' => 5, 'group_id' => 4], ['id' => 6, 'group_id' => 4]])->run())->toBe(2);
    expect(Db::query()->replaceIntoValues('items', ['id' => 5, 'group_id' => 5])->run())->toBe(1);
    expect(Db::query()->update('items', ['group_id' => 7])->where(column('id'), 5)->runReturning('group_id'))->toBe(7);
    expect(Db::query()->insertIntoValues('items', ['id' => 7, 'group_id' => 8])->runReturning(['id', 'group_id']))->toBe(['id' => 7, 'group_id' => 8]);
    expect(Db::query()->update('items')->set('group_id', 9)->where(column('id'), 7)->runReturningRow())->toBe(['id' => 7, 'group_id' => 9]);
    expect(Db::query()->deleteFrom('items')->where(column('id'), 7)->run())->toBe(1);
    expect(Db::query()->insertInto('items', ['id', 'group_id'])->values([8, 0])->run())->toBe(1);
});

it('rejects incomplete inserts, negative pagination and absent scalar results', function (): void {
    foreach ([fn () => Db::query()->insertIntoValues('items', []), fn () => Db::query()->insertInto('items', []),
        fn () => Db::query()->insertIgnoreIntoValues('items', []), fn () => Db::query()->limit(-1),
        fn () => Db::query()->limit(1, -1), fn () => Db::query()->offset(-1)] as $operation) {
        expect($operation)->toThrow(Raxos\Database\Query\Error\IncompleteException::class);
    }
    expect(fn () => Db::query()->select()->from('items')->where(column('id'), 99)->singleOrFail())->toThrow(Raxos\Database\Query\Error\MissingResultException::class);
});

it('selects aliased expressions, scalar literals and merged fields without changing parameter values', function (): void {
    $query = Db::query()->select(['identifier' => column('id'), 'total' => Raxos\Database\Query\Expr::add(column('group_id'), 2), 'zero' => 0, 'enabled' => false])
        ->select('group_id')->from('items')->where(column('id'), 1);
    expect($query->single())->toBe(['identifier' => 1, 'total' => 3, 'zero' => 0, 'enabled' => 0, 'group_id' => 1]);
    expect(fn () => Db::query()->select([['bad']]))->toThrow(Raxos\Database\Query\Error\UnsupportedException::class);
    expect(fn () => Db::query()->select(Db::query()->select(1)))->toThrow(Raxos\Database\Query\Error\MissingAliasException::class);
    expect(Db::query()->select(['sub' => Db::query()->select(7)])->single())->toBe(['sub' => 7]);
});

it('executes derived tables, CTEs and unions with bindings intact', function (): void {
    $sub = Db::query()->select('id')->from('items')->where(column('group_id'), '1');
    expect(Db::query()->select('id')->from($sub, 'derived')->orderBy('id')->array())->toBe([['id' => 1], ['id' => 2]]);
    expect(Db::query()->with('unit_cte', $sub)->select('id')->from('unit_cte')->resultCount())->toBe(2);
    $first = Db::query()->select('id')->from('items')->where(column('group_id'), '1');
    $second = Db::query()->select('id')->from('items')->where(column('group_id'), '2');
    expect($first->union($second)->totalCount())->toBe(3);
    expect(Db::query()->select(1)->unionAll(Db::query()->select(1))->totalCount())->toBe(2);
});

it('treats joins as idempotent for the same alias while retaining distinct aliases', function (): void {
    $calls = 0;
    $on = function ($query) use (&$calls): void {
        ++$calls;
        $query->on(column('id', 'lookup'), column('id', 'items'));
    };
    $query = Db::query()->select(['id' => column('id', 'items')])->from('items')->where(column('id', 'items'), 1)
        ->leftJoin('items as lookup', $on)->leftJoin('items as lookup', $on);
    expect($query->single())->toBe(['id' => 1])->and($calls)->toBe(1);
    $sub = Db::query()->select('id')->from('items')->where(column('group_id'), 1);
    $joined = Db::query()->select(['id' => column('id', 'items')])->from('items')
        ->joinSub($sub, 'subset', static fn ($query) => $query->on(column('id', 'subset'), column('id', 'items')))
        ->joinSub($sub, 'subset', static fn () => throw new RuntimeException('duplicate callback'));
    expect($joined->resultCount())->toBe(2);
});

it('supports grouped HAVING predicates, conditional composition and custom pagination builders', function (): void {
    $query = Db::query()->select('group_id', Literal::of('count(*) as members'))->from('items')->groupBy('group_id')
        ->havingIn(column('group_id'), [1, 2])->orHaving(column('group_id'), 3)->orderByDesc('members')->orderByAsc('group_id');
    expect(array_column($query->array(), 'group_id'))->toBe([1, 2, 3]);
    $calls = 0;
    $query->conditional(false, function () use (&$calls): void {
        ++$calls;
    });
    $query->conditionalParenthesis(false, function () use (&$calls): void {
        ++$calls;
    });
    expect($calls)->toBe(0);
    $page = Db::query()->select()->from('items')->paginate(2, 2, static fn ($query, $offset, $limit) => $query->limit($limit, $offset)->arrayList(), static fn () => 4);
    expect($page->items->toArray())->toBe([['id' => 3, 'group_id' => 2], ['id' => 4, 'group_id' => 3]])
        ->and($page->page)->toBe(2)->and($page->pages)->toBe(2)->and($page->total)->toBe(4);
});

it('reports query debug state and removes or replaces sorting clauses consistently', function (): void {
    $query = Db::query()->select('id')->from('items')->orderBy('id')->limit(1);
    $query->removeClause('order by')->replaceClause('limit', static fn ($piece) => new Raxos\Database\Query\Piece($piece->clause, 2));
    expect($query->array())->toHaveCount(2)->and($query->isClauseDefined('order by'))->toBeFalse()
        ->and($query->jsonSerialize())->toBe($query->toSql())->and((string)$query)->toBe($query->toSql())
        ->and($query->__debugInfo()['type'])->toBe('PREPARED QUERY');
    expect(fn () => $query->forShare())->toThrow(Raxos\Database\Query\Error\UnsupportedException::class);
    expect(fn () => $query->forUpdate())->toThrow(Raxos\Database\Query\Error\UnsupportedException::class);
});
