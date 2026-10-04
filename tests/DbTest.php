<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Error\InvalidConnectionException;

covers(Db::class);

it('lazily opens named connections and reopens an explicitly disconnected connection', function (): void {
    $connection = SQLite::createFromInMemory();
    Db::register($connection, 'unit-named');
    try {
        expect($connection->connected)->toBeFalse()->and(Db::get('unit-named'))->toBe($connection)->and($connection->connected)->toBeTrue();
        $connection->disconnect();
        expect(Db::column('SELECT 7', 'unit-named'))->toBe(7)->and($connection->connected)->toBeTrue();
        Db::unregister('unit-named');
        expect(Db::get('unit-named'))->toBeNull();
        expect(fn() => Db::getOrFail('unit-named'))->toThrow(InvalidConnectionException::class);
    } finally {
        Db::unregister('unit-named');
    }
});

it('routes reads, writes, metadata and transactions to the selected connection', function (): void {
    Db::register(SQLite::createFromInMemory(), 'unit-facade');
    try {
        expect(Db::execute('CREATE TABLE unit_facade (id INTEGER PRIMARY KEY, label TEXT)', 'unit-facade'))->toBe(0);
        expect(Db::execute('INSERT INTO unit_facade VALUES (1,\'first\')', 'unit-facade'))->toBe(1)
            ->and(Db::lastInsertId(id: 'unit-facade'))->toBe('1')->and(Db::lastInsertIdInteger(id: 'unit-facade'))->toBe(1)
            ->and(Db::attribute(PDO::ATTR_DRIVER_NAME, 'unit-facade'))->toBe('sqlite')->and(Db::quote("a'b", id: 'unit-facade'))->toBe("'a''b'")
            ->and(Db::prepare('SELECT label FROM unit_facade', id: 'unit-facade')->fetchColumn())->toBe('first')
            ->and(Db::query('unit-facade')->select('label')->from('unit_facade')->single())->toBe(['label' => 'first'])
            ->and(Db::tableExists('unit_facade', 'unit-facade'))->toBeTrue()
            ->and(Db::tableColumns('unit_facade', 'unit-facade'))->toBe(['id', 'label'])
            ->and(Db::tableColumnExists('unit_facade', 'label', 'unit-facade'))->toBeTrue();
        Db::transaction('unit-facade');
        expect(Db::inTransaction('unit-facade'))->toBeTrue()->and(Db::commit('unit-facade'))->toBeTrue();
        Db::transaction('unit-facade');
        expect(Db::rollBack('unit-facade'))->toBeTrue()->and(Db::inTransaction('unit-facade'))->toBeFalse();
    } finally {
        Db::unregister('unit-facade');
    }
});
