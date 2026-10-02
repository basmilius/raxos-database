<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Query;
use function RaxosTests\Database\unitConnection;

covers(Query::class);

it('inserts, ignores duplicates and updates conflicting records through native write clauses', function (string $driver): void {
    $connection = unitConnection($driver);
    $table = 'raxos_unit_write';
    $connection->execute('DROP TABLE IF EXISTS '.$table);
    try {
        $connection->execute('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY, name VARCHAR(100))');
        $connection->query()->insertIntoValues($table, ['id' => 1, 'name' => 'first'])->run();
        $connection->query()->insertIgnoreInto($table, ['id', 'name'])->values([1, 'ignored'])->run();
        expect($connection->query()->select('name')->from($table)->statement()->fetchColumn())->toBe('first');
        $connection->query()->insertIntoValues($table, ['id' => 1, 'name' => 'updated'])->onDuplicateKeyUpdate([new ColumnRef('name')])->run();
        expect($connection->query()->select('name')->from($table)->statement()->fetchColumn())->toBe('updated');
        expect($connection->query()->select()->from($table)->explain())->not->toBeNull();
        $connection->transaction(function () use ($connection, $table): void {
            expect($connection->query()->select()->from($table)->forUpdate()->nowait()->single()['id'])->toBe(1);
            expect($connection->query()->select()->from($table)->forUpdate()->skipLocked()->single()['id'])->toBe(1);
        });
    } finally {
        $connection->execute('DROP TABLE IF EXISTS '.$table);
    }
})->with(['mysql', 'mariadb']);
