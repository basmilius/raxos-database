<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\SubQuery;
use function Raxos\Database\Query\{literal};
use function RaxosTests\Database\{compiledExpression, unitQuery};

covers(SubQuery::class);

it('merges bound subquery values without changing the original query', function (): void {
    $sub = unitQuery()->select(1)->where(literal(1), 'value');
    $before = $sub->toSql();
    [$sql, $params] = compiledExpression(new SubQuery($sub));
    expect($sql)->toStartWith('( select 1 where 1 =')->toEndWith(')')->and($params)->toBe(['value'])->and($sub->toSql())->toBe($before);
});
