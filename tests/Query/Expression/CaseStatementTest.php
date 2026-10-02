<?php
declare(strict_types=1);

use Raxos\Database\Query\Expr;
use Raxos\Database\Query\Expression\CaseStatement;
use function Raxos\Database\Query\{literal};
use function RaxosTests\Database\{unitQuery};

covers(CaseStatement::class);

it('selects the first matching branch and evaluates a fallback or SQL null', function (): void {
    $case = new CaseStatement()->when(Expr::eq(1, 2), literal(10))->when(Expr::eq(2, 2), literal(20))->else(literal(30));
    expect(unitQuery()->select($case)->statement()->fetchColumn())->toBe(20);
    $fallback = new CaseStatement()->when(Expr::eq(1, 2), literal(10))->else(literal(30));
    expect(unitQuery()->select($fallback)->statement()->fetchColumn())->toBe(30);
    expect(unitQuery()->select(new CaseStatement()->when(Expr::eq(1, 2), literal(10)))->statement()->fetchColumn())->toBeNull();
});
