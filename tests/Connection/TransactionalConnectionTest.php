<?php
declare(strict_types=1);

use Raxos\Database\Connection\Connection;
use Raxos\Database\Db;
use Raxos\Database\Query\Error\RollbackOnlyTransactionException;
use function RaxosTests\Database\unitConnection;

covers(Connection::class);

it('returns callback values and defers ordered hooks until the outer native commit', function (string $driver): void {
    $connection = unitConnection($driver);
    $events = [];
    $result = $connection->transactional(function () use ($connection, &$events): string {
        $connection->afterCommit(function () use (&$events): void {
            $events[] = 'first';
        });
        $connection->transactional(function () use ($connection, &$events): void {
            $connection->afterCommit(function () use (&$events): void {
                $events[] = 'nested';
            });
        });
        expect($events)->toBe([])->and($connection->inTransaction)->toBeTrue();

        return 'result';
    });
    expect($result)->toBe('result')->and($events)->toBe(['first', 'nested'])->and($connection->inTransaction)->toBeFalse();
    $connection->afterCommit(function () use (&$events): void {
        $events[] = 'immediate';
    });
    expect($events)->toBe(['first', 'nested', 'immediate']);
})->with(['sqlite', 'mysql', 'mariadb']);

it('rolls back failures, clears hooks and preserves the original cause', function (string $driver): void {
    $connection = unitConnection($driver);
    $called = false;
    $cause = new LogicException('original');

    try {
        $connection->transactional(function () use ($connection, &$called, $cause): void {
            $connection->afterCommit(function () use (&$called): void {
                $called = true;
            });

            throw $cause;
        });
        test()->fail('The callback should fail.');
    } catch (LogicException $error) {
        expect($error)->toBe($cause);
    }
    expect($called)->toBeFalse()->and($connection->inTransaction)->toBeFalse();
    expect($connection->transactional(static fn() => 42))->toBe(42)->and($called)->toBeFalse();
})->with(['sqlite', 'mysql', 'mariadb']);

it('discards every hook when a caught nested failure marks the transaction rollback-only', function (): void {
    $connection = unitConnection();
    $called = false;
    expect(function () use ($connection, &$called): void {
        $connection->transactional(function () use ($connection, &$called): void {
            $connection->afterCommit(function () use (&$called): void {
                $called = true;
            });

            try {
                $connection->transactional(static fn() => throw new LogicException('nested'));
            } catch (LogicException) {
            }
        });
    })->toThrow(RollbackOnlyTransactionException::class);
    expect($called)->toBeFalse()->and($connection->inTransaction)->toBeFalse();
});

it('runs later hooks even if one fails and never rolls back a committed transaction', function (): void {
    $connection = unitConnection();
    $events = [];
    expect(function () use ($connection, &$events): void {
        $connection->transactional(function () use ($connection, &$events): void {
            $connection->afterCommit(static fn() => throw new LogicException('hook'));
            $connection->afterCommit(function () use (&$events): void {
                $events[] = 'later';
            });
        });
    })->toThrow(LogicException::class, 'hook');
    expect($events)->toBe(['later'])->and($connection->inTransaction)->toBeFalse();
    expect(Db::transactional(static fn() => 'db'))->toBe('db');
});

it('rolls back a deferred constraint failure at commit without running commit hooks', function (): void {
    $connection = unitConnection();
    $connection->execute('PRAGMA foreign_keys = ON');
    $connection->execute('CREATE TABLE tx_parent (id INTEGER PRIMARY KEY)');
    $connection->execute('CREATE TABLE tx_child (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES tx_parent(id) DEFERRABLE INITIALLY DEFERRED)');
    $called = false;
    expect(fn() => $connection->transactional(function () use ($connection, &$called): void {
        $connection->execute('INSERT INTO tx_child VALUES (1, 999)');
        $connection->afterCommit(function () use (&$called): void {
            $called = true;
        });
    }))->toThrow(PDOException::class);
    expect($called)->toBeFalse()->and($connection->inTransaction)->toBeFalse()
        ->and($connection->column('SELECT COUNT(*) FROM tx_child'))->toBe(0)
        ->and($connection->transactional(static fn(): int => 42))->toBe(42);
});

it('keeps commit hooks isolated between independently opened connections', function (): void {
    $first = unitConnection();
    $second = unitConnection();
    $events = [];
    $first->transaction();
    $second->transaction();
    $first->afterCommit(function () use (&$events): void {
        $events[] = 'first';
    });
    $second->afterCommit(function () use (&$events): void {
        $events[] = 'second';
    });
    $second->commit();
    expect($events)->toBe(['second'])->and($first->inTransaction)->toBeTrue();
    $first->rollBack();
    expect($events)->toBe(['second']);
});
