<?php
declare(strict_types=1);

use Raxos\Database\Query\Error\{InvalidModelException, MissingModelException};
use Raxos\Database\Query\Statement;
use RaxosTests\Database\ParentModel;
use function RaxosTests\Database\{unitConnection, unitOrm};

covers(Statement::class);

it('binds native values and returns both associative and object fetch shapes', function (): void {
    $connection = unitConnection();
    $statement = new Statement($connection, 'SELECT :bool AS enabled, :int AS count, :str AS label, :null AS absent');
    $statement->bind(':bool', false)->bind(':int', 0)->bind(':str', "a'b")->bind(':null', null);
    expect($statement->single())->toBe(['enabled' => 0, 'count' => 0, 'label' => "a'b", 'absent' => null]);
    $row = $statement->single(PDO::FETCH_OBJ);
    expect($row)->toBeInstanceOf(stdClass::class)->and($row->label)->toBe("a'b");
    expect($statement->fetch())->toBeNull();
});

it('streams actual rows and leaves the connection usable after early termination', function (): void {
    $connection = unitConnection();
    $statement = new Statement($connection, 'SELECT 1 AS value UNION ALL SELECT 2');
    expect(iterator_to_array($statement->cursor()))->toBe([['value' => 1], ['value' => 2]]);
    $cursor = $statement->cursor();
    foreach ($cursor as $row) {
        expect($row['value'])->toBe(1);
        break;
    }
    unset($cursor);
    expect($connection->column('SELECT 3'))->toBe(3);
    expect(fn () => iterator_to_array($statement->cursor(batchSize: 0)))->toThrow(Raxos\Error\InvalidArgumentException::class);
});

it('supports execution counts, arrays, collections and SQL null or no-row scalars', function (): void {
    $connection = unitConnection();
    $connection->execute('CREATE TABLE unit_statement (id INTEGER)');
    $statement = new Statement($connection, 'INSERT INTO unit_statement VALUES (1),(2)');
    expect($statement->run())->toBe(2)->and($statement->rowCount())->toBe(2)->and($statement->sql)->toBe('INSERT INTO unit_statement VALUES (1),(2)');
    $select = new Statement($connection, 'SELECT id FROM unit_statement ORDER BY id');
    expect($select->array())->toBe([['id' => 1], ['id' => 2]])->and($select->arrayList()->toArray())->toBe([['id' => 1], ['id' => 2]]);
    expect(new Statement($connection, 'SELECT NULL')->fetchColumn())->toBeNull();
    expect(new Statement($connection, 'SELECT id FROM unit_statement WHERE id = 99')->fetchColumn())->toBeFalse();
});

it('hydrates models and preserves the identity cache across repeated reads', function (): void {
    $connection = unitOrm();
    $statement = new Statement($connection, 'SELECT * FROM parents WHERE id = 1')->withModel(ParentModel::class);
    $first = $statement->single();
    expect($first)->toBeInstanceOf(ParentModel::class)->and($first->name)->toBe('first')
        ->and($statement->single()->backbone)->toBe($first->backbone)->and($statement->arrayList())->toBeInstanceOf(Raxos\Database\Orm\ModelArrayList::class);
    expect(fn () => $statement->createModel(new stdClass()))->toThrow(InvalidModelException::class);
    $statement->withoutModel();
    expect($statement->single())->toBe(['id' => 1, 'external_key' => 100, 'name' => 'first']);
    expect(fn () => $statement->createModel([]))->toThrow(InvalidModelException::class);
    $statement->withModel('MissingUnitModel');
    expect(fn () => $statement->createModel([]))->toThrow(InvalidModelException::class);
});

it('rejects eager-loading without a model and logs only while the logger is enabled', function (): void {
    $connection = unitConnection();
    $statement = new Statement($connection, 'SELECT 1');
    $statement->eagerLoad(['children']);
    expect(fn () => $statement->array())->toThrow(MissingModelException::class);
    $statement->eagerLoad([]);
    $statement->array();
    expect($connection->logger->count())->toBe(0);
    $connection->logger->enable();
    $statement->array();
    expect($connection->logger->count())->toBe(1);
    $connection->logger->disable();
    $statement->array();
    expect($connection->logger->count())->toBe(1);
});
