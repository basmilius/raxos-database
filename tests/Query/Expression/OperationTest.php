<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\Operation;
use function Raxos\Database\Query\{column};
use function RaxosTests\Database\{compiledExpression, unitQuery};

covers(Operation::class);

it('executes comparisons and arithmetic without treating identifiers as values', function (): void {
    $query = unitQuery()->select(['result' => new Operation('+', 4, 5)]);
    expect($query->statement()->fetchColumn())->toBe(9);
    [$sql, $params] = compiledExpression(new Operation('=', column('id', 'items'), 'unsafe'));
    expect($sql)->toMatch('/^`items`\.`id` = :p\d+_0$/')->and($params)->toBe(['unsafe']);
});
