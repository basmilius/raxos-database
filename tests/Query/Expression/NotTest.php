<?php
declare(strict_types=1);

use Raxos\Database\Query\Expr;
use Raxos\Database\Query\Expression\Not;
use function RaxosTests\Database\{unitQuery};

covers(Not::class);

it('negates a nested predicate and preserves its value bindings', function (): void {
    $query = unitQuery()->select(['result' => new Not(Expr::eq(1, 1))]);
    expect($query->statement()->fetchColumn())->toBe(0);
});
