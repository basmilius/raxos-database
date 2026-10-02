<?php
declare(strict_types=1);

use Raxos\Database\Query\Expr;
use Raxos\Database\Query\Expression\Exists;
use function Raxos\Database\Query\{literal};
use function RaxosTests\Database\{unitQuery};

covers(Exists::class);

it('evaluates both empty and nonempty subqueries', function (int $value, int $expected): void {
    $sub = unitQuery()->select(1)->where(literal(1), $value);
    expect(unitQuery()->select(new Exists(Expr::subQuery($sub)))->statement()->fetchColumn())->toBe($expected);
})->with([[1, 1], [2, 0]]);
