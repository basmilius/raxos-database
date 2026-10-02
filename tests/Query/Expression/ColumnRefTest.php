<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\ColumnRef;
use function RaxosTests\Database\{compiledExpression};

covers(ColumnRef::class);

it('escapes schema, table and column with the compiling grammar', function (): void {
    expect(compiledExpression(new ColumnRef('name', 'people', 'main')))->toBe(['`main`.`people`.`name`', []]);
    expect(compiledExpression(new ColumnRef('*', 'people')))->toBe(['`people`.*', []]);
    $column = new ColumnRef('id', 'people');
    $foreign = $column->asForeignKeyForTable('orders');
    expect($foreign->column)->toBe('people_id')->and($foreign->table)->toBe('orders')->and($column->column)->toBe('id');
});
