<?php
declare(strict_types=1);

use Raxos\Database\Grammar\{Grammar, MySqlGrammar, SQLiteGrammar};

covers(Grammar::class);

it('separates identifier caches for differently configured grammars of the same class', function (): void {
    $first = new MySqlGrammar();
    $second = new MySqlGrammar(escapers: ['[', ']']);
    expect($first->escape('unit_cache'))->toBe('`unit_cache`')->and($second->escape('unit_cache'))->toBe('[unit_cache]')
        ->and($first->escape('0'))->toBe('`0`');
});

it('retains correct escaping after the bounded cache has been recycled', function (): void {
    $grammar = new SQLiteGrammar();
    for ($index = 0; $index < 4100; ++$index) {
        expect($grammar->escape('unit_' . $index))->toBe('`unit_' . $index . '`');
    }
    expect($grammar->escape('unit_0'))->toBe('`unit_0`');
});
