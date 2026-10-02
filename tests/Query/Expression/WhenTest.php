<?php
declare(strict_types=1);

use Raxos\Database\Query\Expr;
use Raxos\Database\Query\Expression\When;
use function Raxos\Database\Query\{column, literal};
use function RaxosTests\Database\{compiledExpression};

covers(When::class);

it('compiles a conditional branch for use inside CASE', function (): void {
    [$sql, $params] = compiledExpression(new When(Expr::eq(column('state'), 'active'), literal(7)));
    expect($sql)->toMatch('/^when `state` = :p\d+_0 then 7$/')->and($params)->toBe(['active']);
});
