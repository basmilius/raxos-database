<?php
declare(strict_types=1);

use Raxos\Database\Grammar\SQLiteGrammar;
use Raxos\Database\Query\Error\UnsupportedException;

covers(SQLiteGrammar::class);

it('escapes identifiers, qualified names, aliases and expressions consistently', function (string $input, string $expected): void {
    $grammar = new SQLiteGrammar();
    expect($grammar->escape($input))->toBe($expected)->and($grammar->escape($input))->toBe($expected);
})->with([['name', '`name`'], ['items.name', '`items`.`name`'], ['items.*', '`items`.*'], ['name as label', '`name` as label'], ['count(*)', 'count(*)'], ['`name`', '`name`'], ['', '']]);

it('advertises the engine capabilities and row-locking behavior', function (): void {
    $grammar = new SQLiteGrammar();
    expect($grammar->supportsReturning)->toBe(true)
        ->and($grammar->supportsRowLocking)->toBe(false);

    foreach (['compileForShare', 'compileForUpdate', 'compileLockNowait', 'compileLockSkipLocked'] as $method) {
        expect(fn() => $grammar->$method())->toThrow(UnsupportedException::class);
    }
    expect(fn() => $grammar->compileOptimizeTable('items'))->toThrow(UnsupportedException::class);
    expect(fn() => $grammar->compileTruncateTable('items'))->toThrow(UnsupportedException::class);
});
