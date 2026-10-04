<?php
declare(strict_types=1);

use Raxos\Database\Connection\MariaDb;
use Raxos\Database\Error\{InvalidOptionException, InvalidTableException, MissingOptionException};
use function RaxosTests\Database\unitConnection;

covers(MariaDb::class);

it('creates the correct driver builder without opening a connection', function (): void {
    $connection = MariaDb::createFromOptions(host: 'localhost', port: 3306, database: 'unit');
    expect($connection->connected)->toBeFalse()->and($connection->query())->toBeInstanceOf(Raxos\Database\Query\MariaDbQuery::class);
});

it('loads real table schemas and distinguishes unknown tables and columns', function (): void {
    $connection = unitConnection('mariadb');
    try {
        $connection->execute('DROP TABLE IF EXISTS raxos_unit_schema');
        $connection->execute('CREATE TABLE raxos_unit_schema (id INTEGER PRIMARY KEY, label VARCHAR(20))');
        expect($connection->ping())->toBeTrue()
            ->and($connection->tableExists('raxos_unit_schema'))->toBeTrue()
            ->and($connection->tableColumns('raxos_unit_schema'))->toBe(['id', 'label'])
            ->and($connection->tableColumnExists('raxos_unit_schema', 'label'))->toBeTrue()
            ->and($connection->tableColumnExists('raxos_unit_schema', 'missing'))->toBeFalse()
            ->and($connection->tableExists('raxos_nonexistent_unit'))->toBeFalse();
        expect(fn() => $connection->tableColumns('raxos_nonexistent_unit'))->toThrow(InvalidTableException::class);
        expect($connection->loadDatabaseSchema()['raxos_unit_schema'])->toBe(['id', 'label']);
    } finally {
        $connection->execute('DROP TABLE IF EXISTS raxos_unit_schema');
    }
});


it('rejects incomplete and conflicting connection options', function (): void {
    expect(fn() => MariaDb::createFromOptions())->toThrow(MissingOptionException::class);
    expect(fn() => MariaDb::createFromOptions(host: 'localhost'))->toThrow(MissingOptionException::class);
    expect(fn() => MariaDb::createFromOptions(host: 'localhost', database: 'unit', unixSocket: '/tmp/unit.sock'))->toThrow(InvalidOptionException::class);
    expect(MariaDb::createFromOptions(database: 'unit', unixSocket: '/tmp/unit.sock')->dsn)->toBe('mysql:charset=utf8mb4;dbname=unit;unix_socket=/tmp/unit.sock;');
});
