<?php
declare(strict_types=1);

use Raxos\Database\Query\Partial;
use function Raxos\Database\Query\literal;
use function RaxosTests\Database\unitQuery;

covers(Partial::class);

it('builds the deferred subquery with the compiling connection', function (): void {
    $query = unitQuery();
    $seen = null;
    $partial = new Partial(function ($connection) use (&$seen) {
        $seen = $connection;
        return $connection->query()->select(7)->where(literal(1), 1);
    });
    expect($query->select($partial)->statement()->fetchColumn())->toBe(7)->and($seen)->toBe($query->connection);
});
