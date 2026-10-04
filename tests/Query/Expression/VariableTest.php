<?php
declare(strict_types=1);

use Raxos\Database\Query\Expr;
use Raxos\Database\Query\Expression\Variable;
use function RaxosTests\Database\{compiledExpression};

covers(Variable::class);

it('rejects SQL delimiters in variable names', function (string $name): void {
    expect(fn() => new Variable($name, Expr::abs(-1)))->toThrow(InvalidArgumentException::class);
})->with(['', '1first', 'user; drop table users', 'user name', '@user', 'user`']);

it('compiles valid variable names without binding the identifier', function (): void {
    expect(compiledExpression(new Variable('stats.total', Expr::abs(-1))))->toBe(['@stats.total := abs( -1 )', []]);
});
