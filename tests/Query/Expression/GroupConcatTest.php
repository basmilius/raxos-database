<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\GroupConcat;
use function Raxos\Database\Query\{column};
use function RaxosTests\Database\{compiledExpression};

covers(GroupConcat::class);

it('compiles ordering, separator and MariaDB pagination independently', function (): void {
    [$sql, $params] = compiledExpression(new GroupConcat(column('name'), distinct: true, orderBy: column('id'), separator: ';', limit: 2, offset: 1));
    expect($sql)->toContain('group_concat( distinct `name` order by `id` separator')->toContain('limit 2')->toContain('offset 1')->and($params)->toBe([]);
    expect(compiledExpression(new GroupConcat(column('name')))[0])->toBe('group_concat( `name` )');
});
