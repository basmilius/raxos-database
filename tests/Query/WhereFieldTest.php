<?php
declare(strict_types=1);

use Raxos\Database\Query\Query;
use function RaxosTests\Database\unitConnection;

covers(Query::class);

it('separates field identifiers from string values on all drivers', function (string $driver): void {
    $connection = unitConnection($driver);
    $table = 'raxos_unit_field';
    $connection->execute('DROP TABLE IF EXISTS ' . $table);

    try {
        $connection->execute('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, name VARCHAR(100))');
        $connection->execute("INSERT INTO " . $table . " VALUES (1, 'before'), (10, 'after')");
        $query = $connection->query()->select()->from($table)->whereField('id', '>', 5);
        expect(array_column($query->array(), 'id'))->toBe([10]);
        expect($connection->query()->select()->from($table)->where('id', 'name')->array())->toBe([]);
        expect($connection->query()->select()->from($table)->whereField('name', 'before')->single()['id'])->toBe(1);
    } finally {
        $connection->execute('DROP TABLE IF EXISTS ' . $table);
    }
})->with(['sqlite', 'mysql', 'mariadb']);
