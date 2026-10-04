<?php
declare(strict_types=1);

use Raxos\Database\Connection\MySql;
use Raxos\Database\Error\{InvalidOptionException, InvalidTableException, MissingOptionException};
use function RaxosTests\Database\unitConnection;

covers(MySql::class);

it('creates the correct driver builder without opening a connection', function (): void {
    $connection = MySql::createFromOptions(host: 'localhost', port: 3306, database: 'unit');
    expect($connection->connected)->toBeFalse()->and($connection->query())->toBeInstanceOf(Raxos\Database\Query\MySqlQuery::class);
});

it('loads real table schemas and distinguishes unknown tables and columns', function (): void {
    $connection = unitConnection('mysql');
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
    expect(fn() => MySql::createFromOptions())->toThrow(MissingOptionException::class);
    expect(fn() => MySql::createFromOptions(host: 'localhost'))->toThrow(MissingOptionException::class);
    expect(fn() => MySql::createFromOptions(host: 'localhost', database: 'unit', unixSocket: '/tmp/unit.sock'))->toThrow(InvalidOptionException::class);
    expect(MySql::createFromOptions(database: 'unit', unixSocket: '/tmp/unit.sock')->dsn)->toBe('mysql:charset=utf8mb4;dbname=unit;unix_socket=/tmp/unit.sock;');
});
