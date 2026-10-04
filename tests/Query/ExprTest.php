<?php
declare(strict_types=1);

use Raxos\Database\Query\Expr;
use function RaxosTests\Database\unitQuery;

covers(Expr::class);

it('evaluates public arithmetic, comparisons and common scalar helpers through PDO', function (Closure $build, mixed $expected): void {
    $query = unitQuery()->select(['result' => $build()]);
    expect($query->statement()->fetchColumn())->toEqual($expected);
})->with([
    [static fn() => Expr::add(4, 5), 9], [static fn() => Expr::add(4, -5), -1],
    [static fn() => Expr::mul(4, 5), 20], [static fn() => Expr::div(20, 5), 4],
    [static fn() => Expr::eq(4, 4), 1], [static fn() => Expr::gt(4, 5), 0],
    [static fn() => Expr::gte(4, 4), 1], [static fn() => Expr::lt(4, 5), 1], [static fn() => Expr::lte(5, 4), 0],
    [static fn() => Expr::abs(-5), 5], [static fn() => Expr::round(1.234, 2), 1.23],
    [static fn() => Expr::ifNull('value', 'fallback'), 'value'], [static fn() => Expr::nullIf('same', 'same'), null],
    [static fn() => Expr::coalesce('first', 'second'), 'first'], [static fn() => Expr::count(), 1],
]);
