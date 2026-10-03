<?php
declare(strict_types=1);

use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Connection\{MariaDb, MySql};
use Raxos\Database\Db;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Error\UnexpectedException;
use Raxos\Database\Query\Expr;
use Raxos\Database\Query\Literal\Literal;
use RaxosTests\Database\CountedModel;
use function Raxos\Database\Query\column;

dataset('mysql servers', ['mysql' => 'RAXOS_MYSQL_DSN', 'mariadb' => 'RAXOS_MARIADB_DSN']);

function countTestConnection(string $variable): MariaDb|MySql
{
    $dsn = getenv($variable);
    if ($dsn === false || $dsn === '') {
        test()->markTestSkipped("{$variable} is not configured.");
    }
    $class = $variable === 'RAXOS_MARIADB_DSN' ? MariaDb::class : MySql::class;
    $connection = new $class($dsn, getenv('RAXOS_MYSQL_USER') ?: 'root', getenv('RAXOS_MYSQL_PASSWORD') ?: '');
    Db::register($connection);
    $connection->connect();
    $connection->pdo->exec('DROP TABLE IF EXISTS raxos_test_counts');
    $connection->pdo->exec('CREATE TABLE raxos_test_counts (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, quantity INTEGER NOT NULL, deleted_at VARCHAR(30) NULL)');
    $connection->pdo->exec("INSERT INTO raxos_test_counts VALUES (1,1,1,NULL),(2,1,3,NULL),(3,2,5,NULL),(4,3,7,'2026-01-01')");
    $connection->logger->enable();
    return $connection;
}

afterEach(function (): void {
    if (isset($this->connection)) {
        $this->connection->pdo->exec('DROP TABLE IF EXISTS raxos_test_counts');
    }
});

it('counts a wildcard and a computed override without duplicate derived-table columns', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    $query = CountedModel::select()->orderBy(CountedModel::col('id'))->limit(2, 1);
    $sql = $query->toSql();
    expect($query->resultCount())->toBe(2)
        ->and($query->totalCount())->toBe(3)
        ->and($query->toSql())->toBe($sql)
        ->and($query->arrayList()[0]->quantity)->toBe(3);
    expect(CountedModel::select()->withDeleted()->totalCount())->toBe(4);
})->with('mysql servers');

it('serializes a pagination visibility callback with a computed column override', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    $page = CountedModel::select()->orderBy(CountedModel::col('id'))->paginate(0, 2, static fn(QueryInterface $query, int $offset, int $limit): ModelArrayList => $query
        ->limit($limit, $offset)
        ->arrayList()
        ->makeVisible('quantity'));
    $data = json_decode(json_encode($page, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    expect($data['items'])->toHaveCount(2)
        ->and($data['items'][0]['quantity'])->toBe(2)
        ->and($data['total'])->toBe(3)
        ->and($data['pages'])->toBe(2);
})->with('mysql servers');

it('executes grouped soft-delete searches with pagination and accurate counts', function (string $variable, bool $firstGroup): void {
    $this->connection = countTestConnection($variable);
    $query = CountedModel::select()->orderBy(CountedModel::col('id'))->limit(1, 1);

    if (!$firstGroup) {
        $query->where(CountedModel::col('id'), '>', 0);
    }

    $query->parenthesis(static fn ($query) => $query->where(CountedModel::col('group_id'), 1)->orWhere(CountedModel::col('group_id'), 3));
    $sql = $query->toSql();

    expect(array_column($query->array(), 'id'))->toBe([2])
        ->and($query->resultCount())->toBe(1)
        ->and($query->totalCount())->toBe(2)
        ->and($query->toSql())->toBe($sql)
        ->and((clone $query)->withDeleted()->totalCount())->toBe(3);
})->with('mysql servers')->with([true, false]);

it('counts joined wildcards and duplicate explicit aliases with bound predicates', function (string $variable, bool $wildcard): void {
    $this->connection = countTestConnection($variable);
    $query = Db::query()->select($wildcard ? [Literal::of('a.*'), Literal::of('b.*')] : [Literal::of('a.id as duplicate'), Literal::of('b.id as duplicate')])
        ->from('raxos_test_counts', 'a')
        ->join('raxos_test_counts as b', static fn(QueryInterface $query): QueryInterface => $query->on(column('group_id', 'a'), column('group_id', 'b')))
        ->where(column('group_id', 'a'), 1)
        ->orderBy('a.id')
        ->limit(2, 1);
    $sql = $query->toSql();
    expect($query->resultCount())->toBe(2)
        ->and($query->totalCount())->toBe(4)
        ->and($query->toSql())->toBe($sql);
})->with('mysql servers')->with([true, false]);

it('balances fulltext expression fragments before soft-delete filters and late predicates', function (string $variable, bool $lateGroup): void {
    $this->connection = countTestConnection($variable);
    $this->connection->pdo->exec('ALTER TABLE raxos_test_counts ADD search_text TEXT, ADD FULLTEXT INDEX (search_text)');
    $this->connection->pdo->exec("UPDATE raxos_test_counts SET search_text = 'festival'");
    $query = CountedModel::select()->where(CountedModel::col('id'), '>', 0)
        ->parenthesis(static fn ($query) => $query->where(Expr::matchAgainst(column('search_text', 'raxos_test_counts'), 'festival')))
        ->orderBy(CountedModel::col('id'))->limit(1);

    if ($lateGroup) {
        $query->parenthesis(static fn ($query) => $query->where(CountedModel::col('id'), '>', 1));
    } else {
        $query->where(CountedModel::col('id'), '>', 1);
    }

    $sql = $query->toSql();
    expect(array_column($query->array(), 'id'))->toBe([2])
        ->and($query->resultCount())->toBe(1)
        ->and($query->totalCount())->toBe(2)
        ->and($query->toSql())->toBe($sql);
})->with('mysql servers')->with([true, false]);

it('preserves DISTINCT and HAVING semantics when output names overlap', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    $distinct = Db::query()->selectDistinct('group_id', Literal::of('group_id + 0 as group_id'))->from('raxos_test_counts')->limit(2);
    expect($distinct->resultCount())->toBe(2)->and($distinct->totalCount())->toBe(3);
    $grouped = Db::query()->select('group_id', Literal::of('group_id + 0 as group_id'), Literal::of('count(*) as members'))
        ->from('raxos_test_counts')->groupBy('group_id')->having(Literal::of('members > 1'));
    expect($grouped->totalCount())->toBe(1);
})->with('mysql servers');

it('keeps zero limits, empty results and aggregate queries correct with repeated aliases', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    $query = Db::query()->select(Literal::of('id as duplicate'), Literal::of('quantity as duplicate'))->from('raxos_test_counts')->limit(0);
    expect($query->resultCount())->toBe(0)->and($query->totalCount())->toBe(4);
    $empty = Db::query()->select(Literal::of('id as duplicate'), Literal::of('quantity as duplicate'))->from('raxos_test_counts')->where(column('id'), -1);
    expect($empty->totalCount())->toBe(0);
    $aggregate = Db::query()->select(Literal::of('count(*) as duplicate'), Literal::of('sum(quantity) as duplicate'))->from('raxos_test_counts');
    expect($aggregate->totalCount())->toBe(1);
})->with('mysql servers');

it('uses a single count query when names are unique and propagates unrelated SQL errors', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    expect(Db::query()->select()->from('raxos_test_counts')->totalCount())->toBe(4)
        ->and($this->connection->logger->count())->toBe(1);
    expect(fn(): int => Db::query()->select('does_not_exist')->from('raxos_test_counts')->totalCount())->toThrow(UnexpectedException::class);
})->with('mysql servers');

it('preserves parameters inside the SELECT projection and the WHERE clause', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    $query = Db::query()->select(['*', 'quantity' => Expr::if(column('quantity'), 'high', 'low')])
        ->from('raxos_test_counts')->where(column('quantity'), '>', 2)->limit(1);
    expect($query->resultCount())->toBe(1)->and($query->totalCount())->toBe(3);
})->with('mysql servers');

it('counts UNION ALL rows with duplicate names and independent bindings', function (string $variable): void {
    $this->connection = countTestConnection($variable);
    $makeQuery = static fn(): QueryInterface => Db::query()->select(Literal::of('id as duplicate'), Literal::of('quantity as duplicate'))->from('raxos_test_counts');
    $query = $makeQuery()->where(column('group_id'), 1)->unionAll($makeQuery()->where(column('group_id'), 2))->limit(1, 1);
    expect($query->resultCount())->toBe(1)->and($query->totalCount())->toBe(3);
})->with('mysql servers');
