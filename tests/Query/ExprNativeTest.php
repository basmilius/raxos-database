<?php
declare(strict_types=1);

use Raxos\Database\Query\{DateTimeUnit, Expr};
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use function RaxosTests\Database\unitConnection;

covers(Expr::class);

it('executes scalar, numeric and calendar helpers on both supported native engines', function (string $driver, Closure $build, mixed $expected): void {
    $connection = unitConnection($driver);
    $connection->execute("SET time_zone = '+00:00'");
    $actual = $connection->query()->select(['value' => $build()])->statement()->fetchColumn();
    if (is_int($expected) || is_float($expected)) {
        expect((float)$actual)->toEqualWithDelta($expected, 0.000001);
    } else {
        expect(is_string($actual) && preg_match('/^\d{2}:\d{2}:\d{2}\.000000$/', $actual) ? substr($actual, 0, 8) : $actual)->toBe($expected);
    }
})->with(['mysql', 'mariadb'])->with([
    [static fn () => Expr::greatest(1, 5, 2), 5],
    [static fn () => Expr::least(1, 5, 2), 1],
    [static fn () => Expr::if(1, 'yes', 'no'), 'yes'],
    [static fn () => Expr::date('2026-01-02 03:04:05'), '2026-01-02'],
    [static fn () => Expr::dateAdd('2026-01-02', 2, DateTimeUnit::DAY), '2026-01-04'],
    [static fn () => Expr::dateSub('2026-01-02', 2, DateTimeUnit::DAY), '2025-12-31'],
    [static fn () => Expr::dateFormat('2026-01-02', '%Y-%m'), '2026-01'],
    [static fn () => Expr::datediff('2026-01-04', '2026-01-02'), 2],
    [static fn () => Expr::day('2026-01-02'), 2],
    [static fn () => Expr::dayName('2026-01-02'), 'Friday'],
    [static fn () => Expr::dayOfMonth('2026-01-02'), 2],
    [static fn () => Expr::dayOfWeek('2026-01-02'), 6],
    [static fn () => Expr::dayOfYear('2026-01-02'), 2],
    [static fn () => Expr::extract(DateTimeUnit::YEAR, '2026-01-02'), 2026],
    [static fn () => Expr::fromUnixtime(0), '1970-01-01 00:00:00'],
    [static fn () => Expr::fromUnixtime(0, '%Y'), '1970'],
    [static fn () => Expr::hour('2026-01-02 03:04:05'), 3],
    [static fn () => Expr::lastDay('2024-02-15'), '2024-02-29'],
    [static fn () => Expr::microsecond('03:04:05.123456'), 123456],
    [static fn () => Expr::minute('2026-01-02 03:04:05'), 4],
    [static fn () => Expr::month('2026-01-02'), 1],
    [static fn () => Expr::monthname('2026-01-02'), 'January'],
    [static fn () => Expr::quarter('2026-05-01'), 2],
    [static fn () => Expr::second('2026-01-02 03:04:05'), 5],
    [static fn () => Expr::time('2026-01-02 03:04:05'), '03:04:05'],
    [static fn () => Expr::unixTimestamp('1970-01-01 00:00:01'), 1],
    [static fn () => Expr::week('2026-01-02'), 1],
    [static fn () => Expr::weekDay('2026-01-02'), 4],
    [static fn () => Expr::weekOfYear('2026-01-02'), 1],
    [static fn () => Expr::year('2026-01-02'), 2026],
    [static fn () => Expr::yearWeek('2026-01-02'), 202601],
    [static fn () => Expr::acos(1), 0],
    [static fn () => Expr::asin(0), 0],
    [static fn () => Expr::atan(0), 0],
    [static fn () => Expr::atan2(0, 1), 0],
    [static fn () => Expr::ceil(1.2), 2],
    [static fn () => Expr::ceiling(1.2), 2],
    [static fn () => Expr::cos(0), 1],
    [static fn () => Expr::cot(M_PI / 4), 1],
    [static fn () => Expr::degrees(M_PI), 180],
    [static fn () => Expr::exp(0), 1],
    [static fn () => Expr::floor(-1.2), -2],
    [static fn () => Expr::ln(1), 0],
    [static fn () => Expr::log(8, 2), 3],
    [static fn () => Expr::log(1), 0],
    [static fn () => Expr::log10(100), 2],
    [static fn () => Expr::log2(8), 3],
    [static fn () => Expr::mod(10, 3), 1],
    [static fn () => Expr::oct(8), '10'],
    [static fn () => Expr::pi(), M_PI],
    [static fn () => Expr::pow(2, 3), 8],
    [static fn () => Expr::radians(180), M_PI],
    [static fn () => Expr::round(1.234, 2), 1.23],
    [static fn () => Expr::sign(-1), -1],
    [static fn () => Expr::sin(0), 0],
    [static fn () => Expr::sqrt(9), 3],
    [static fn () => Expr::tan(0), 0],
    [static fn () => Expr::truncate(1.239, 2), 1.23],
    [static fn () => Expr::concat(['a', 'b']), 'ab'],
    [static fn () => Expr::concatWs('-', ['a', 'b']), 'a-b'],
    [static fn () => Expr::sha1('message'), sha1('message')],
    [static fn () => Expr::func('upper', ['value']), 'VALUE'],
]);

it('evaluates aggregates and ordered group concatenation without losing distinct semantics', function (string $driver): void {
    $connection = unitConnection($driver);
    $source = $connection->query()->select(['value' => 1])->unionAll($connection->query()->select(['value' => 1]))->unionAll($connection->query()->select(['value' => 2]));
    $query = $connection->query()->select([
        'average' => Expr::avg(Expr::func('abs', [new ColumnRef('value')]), true),
        'count' => Expr::count(new ColumnRef('value'), true), 'sum' => Expr::sum(new ColumnRef('value')),
        'minimum' => Expr::min(new ColumnRef('value')), 'maximum' => Expr::max(new ColumnRef('value')),
        'joined' => Expr::groupConcat(new ColumnRef('value'), true, Literal::of('value desc'), '|'),
    ])->from($source, 'numbers');
    $row = $query->single();
    expect((float)$row['average'])->toBe(1.5)->and((int)$row['count'])->toBe(2)->and((int)$row['sum'])->toBe(4)
        ->and((int)$row['minimum'])->toBe(1)->and((int)$row['maximum'])->toBe(2)->and($row['joined'])->toBe('2|1');
})->with(['mysql', 'mariadb']);

it('uses server clock functions consistently and retains optional zero-valued parameters', function (string $driver): void {
    $connection = unitConnection($driver);
    $connection->execute("SET time_zone = '+00:00'");
    $row = $connection->query()->select(['date' => Expr::currentDate(), 'time' => Expr::currentTime(), 'timestamp' => Expr::currentTimestamp(), 'now' => Expr::now(), 'unix' => Expr::unixTimestamp(), 'random' => Expr::rand(0)])->single();
    expect($row['date'])->toBe(substr($row['timestamp'], 0, 10))->and($row['time'])->toBe(substr($row['timestamp'], 11))
        ->and($row['now'])->toBe($row['timestamp'])->and(abs((int)$row['unix'] - time()))->toBeLessThan(5)
        ->and((float)$row['random'])->toBeGreaterThanOrEqual(0)->toBeLessThan(1);
})->with(['mysql', 'mariadb']);

it('escapes quotes and backslashes in native GROUP_CONCAT separators', function (string $driver, string $separator): void {
    $connection = unitConnection($driver);
    $source = $connection->query()->select(['value' => Literal::string('a')])->unionAll($connection->query()->select(['value' => Literal::string('b')]));
    $value = $connection->query()->select(['joined' => Expr::groupConcat(new ColumnRef('value'), orderBy: Literal::of('value'), separator: $separator)])
        ->from($source, 'values_to_join')->single()['joined'];
    expect($value)->toBe('a' . $separator . 'b');
})->with(['mysql', 'mariadb'])->with(["'", "\\", "'; select 1; --"]);
