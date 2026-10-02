<?php
declare(strict_types=1);

use Raxos\Database\Connection\{Connection, SQLite};
use Raxos\Database\Error\{ExecutionException, NotConnectedException};
use Raxos\Database\Query\Error\{NotInTransactionException, RollbackOnlyTransactionException};
use function RaxosTests\Database\unitConnection;

covers(Connection::class);

it('requires a connection for reads, writes, quoting and transaction operations', function (string $method, array $args): void {
    $connection = SQLite::createFromInMemory();
    expect($connection->connected)->toBeFalse()->and($connection->inTransaction)->toBeFalse();
    expect(fn () => $connection->$method(...$args))->toThrow(NotConnectedException::class);
})->with([['column', ['select 1']], ['execute', ['select 1']], ['quote', ['value']], ['attribute', [PDO::ATTR_DRIVER_NAME]], ['lastInsertId', []], ['lastInsertIdInteger', []], ['transaction', []], ['commit', []], ['rollBack', []]]);

it('commits nested savepoints and rolls back the whole outer transaction after an inner failure', function (): void {
    $connection = unitConnection();
    $connection->execute('CREATE TABLE unit_transactions (id INTEGER PRIMARY KEY)');
    $connection->transaction();
    $connection->execute('INSERT INTO unit_transactions VALUES (1)');
    $connection->transaction();
    $connection->execute('INSERT INTO unit_transactions VALUES (2)');
    expect($connection->commit())->toBeTrue()->and($connection->inTransaction)->toBeTrue();
    expect($connection->commit())->toBeTrue()->and($connection->inTransaction)->toBeFalse()->and($connection->column('SELECT COUNT(*) FROM unit_transactions'))->toBe(2);
    $connection->transaction();
    $connection->execute('INSERT INTO unit_transactions VALUES (3)');
    $connection->transaction();
    $connection->execute('INSERT INTO unit_transactions VALUES (4)');
    $connection->rollBack();
    expect(fn () => $connection->commit())->toThrow(RollbackOnlyTransactionException::class);
    expect($connection->column('SELECT COUNT(*) FROM unit_transactions'))->toBe(2)->and($connection->inTransaction)->toBeFalse();
    expect(fn () => $connection->commit())->toThrow(NotInTransactionException::class);
    expect(fn () => $connection->rollBack())->toThrow(NotInTransactionException::class);
    $connection->transaction();
    expect($connection->rollBack())->toBeTrue();
});

it('executes bound builders, exposes insert IDs and quotes input through PDO', function (): void {
    $connection = unitConnection();
    $connection->execute('CREATE TABLE unit_writes (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    expect($connection->execute($connection->query()->insertIntoValues('unit_writes', ['name' => "a'b"])))->toBe(1);
    expect($connection->lastInsertId())->toBe('1')->and($connection->lastInsertIdInteger())->toBe(1)
        ->and($connection->column($connection->query()->select('name')->from('unit_writes')))->toBe("a'b")
        ->and($connection->quote("a'b"))->toBe("'a''b'")->and($connection->attribute(PDO::ATTR_DRIVER_NAME))->toBe('sqlite');
    $connection->disconnect();
    expect($connection->connected)->toBeFalse();
});

it('wraps syntax and uniqueness failures with the PDO cause', function (): void {
    $connection = unitConnection();
    foreach (['BAD SQL', 'SELECT missing FROM nonexistent'] as $sql) {
        try {
            $connection->execute($sql);
        } catch (ExecutionException $error) {
            expect($error->getPrevious())->toBeInstanceOf(PDOException::class);
            continue;
        }
        test()->fail('Expected a database error.');
    }
    $connection->autoReconnect = true;
    expect(fn () => $connection->column('BAD SQL'))->toThrow(ExecutionException::class);
});
