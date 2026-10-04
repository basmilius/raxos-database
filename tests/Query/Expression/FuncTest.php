<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\Func;
use Raxos\Database\Query\Query;
use function RaxosTests\Database\{compiledExpression, unitQuery};

covers(Func::class);

it('evaluates nested functions and binds string arguments', function (): void {
    $query = unitQuery()->select(['result' => new Func('upper', ['héllo'])]);
    expect($query->statement()->fetchColumn())->toBe('HéLLO')->and(array_values(new ReflectionProperty(Query::class, 'params')->getValue($query)))->toBe(['héllo']);
    expect(compiledExpression(new Func('abs', [-2]))[0])->toBe('abs( -2 )');
});
