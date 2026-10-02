<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\AggregateFunc;
use function Raxos\Database\Query\{column};
use function RaxosTests\Database\{unitQuery};

covers(AggregateFunc::class);

it('supports distinct aggregation on actual rows', function (bool $distinct, int $expected): void {
    $connection = unitQuery()->connection;
    $connection->execute('CREATE TABLE quantities (quantity INTEGER)');
    $connection->execute('INSERT INTO quantities VALUES (2),(2),(3)');
    $query = $connection->query()->select(new AggregateFunc('sum', [column('quantity')], $distinct))->from('quantities');
    expect($query->statement()->fetchColumn())->toBe($expected);
})->with([[false, 7], [true, 5]]);
