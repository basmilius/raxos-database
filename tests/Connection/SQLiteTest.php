<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Error\{InvalidTableException};
use function RaxosTests\Database\unitConnection;

covers(SQLite::class);

it('creates the correct driver builder without opening a connection', function (): void {
    $connection = SQLite::createFromInMemory();
    expect($connection->connected)->toBeFalse()->and($connection->query())->toBeInstanceOf(Raxos\Database\Query\SQLiteQuery::class);
});

it('loads real table schemas and distinguishes unknown tables and columns', function (): void {
    $connection = unitConnection('sqlite');
    try {
        $connection->execute('DROP TABLE IF EXISTS raxos_unit_schema');
        $connection->execute('CREATE TABLE raxos_unit_schema (id INTEGER PRIMARY KEY, label VARCHAR(20))');
        expect($connection->ping())->toBeTrue()
            ->and($connection->tableExists('raxos_unit_schema'))->toBeTrue()
            ->and($connection->tableColumns('raxos_unit_schema'))->toBe(['id', 'label'])
            ->and($connection->tableColumnExists('raxos_unit_schema', 'label'))->toBeTrue()
            ->and($connection->tableColumnExists('raxos_unit_schema', 'missing'))->toBeFalse()
            ->and($connection->tableExists('raxos_nonexistent_unit'))->toBeFalse();
        expect(fn () => $connection->tableColumns('raxos_nonexistent_unit'))->toThrow(InvalidTableException::class);
        expect($connection->loadDatabaseSchema()['raxos_unit_schema'])->toBe(['id', 'label']);
    } finally {
        $connection->execute('DROP TABLE IF EXISTS raxos_unit_schema');
    }
});


it('supports file connections and explicitly rejects foundRows', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'raxos-sqlite-');
    try {
        $connection = SQLite::createFromFile($file);
        $connection->connect();
        expect($connection->ping())->toBeTrue();
        expect(fn () => $connection->foundRows())->toThrow(Raxos\Database\Query\Error\UnsupportedException::class);
        $connection->disconnect();
        expect($connection->ping())->toBeFalse();
    } finally {
        unlink($file);
    }
});
