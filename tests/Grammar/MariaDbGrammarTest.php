<?php
declare(strict_types=1);

use Raxos\Database\Grammar\MariaDbGrammar;

covers(MariaDbGrammar::class);

it('escapes identifiers, qualified names, aliases and expressions consistently', function (string $input, string $expected): void {
    $grammar = new MariaDbGrammar();
    expect($grammar->escape($input))->toBe($expected)->and($grammar->escape($input))->toBe($expected);
})->with([['name', '`name`'], ['items.name', '`items`.`name`'], ['items.*', '`items`.*'], ['name as label', '`name` as label'], ['count(*)', 'count(*)'], ['`name`', '`name`'], ['', '']]);

it('advertises the engine capabilities and row-locking behavior', function (): void {
    $grammar = new MariaDbGrammar();
    expect($grammar->supportsReturning)->toBe(true)
        ->and($grammar->supportsRowLocking)->toBe(true);

    expect($grammar->compileForShare())->toBe('for share')->and($grammar->compileForUpdate())->toBe('for update')
        ->and($grammar->compileLockNowait())->toBe('nowait')->and($grammar->compileLockSkipLocked())->toBe('skip locked')
        ->and($grammar->compileOptimizeTable('items'))->toBe('optimize table `items`;')->and($grammar->compileTruncateTable('items'))->toBe('truncate table `items`;');
});
