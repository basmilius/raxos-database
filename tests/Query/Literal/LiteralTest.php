<?php
declare(strict_types=1);

use Raxos\Database\Query\Literal\Literal;

covers(Literal::class);

it('preserves explicit literal scalar values', function (mixed $value, string $expected): void {
    expect((string)Literal::of($value))->toBe($expected);
})->with([[0, '0'], [false, ''], [true, '1'], [null, ''], [1.5, '1.5'], ['count(*)', 'count(*)']]);

it('escapes MySQL string literal control characters and quotes', function (): void {
    expect((string)Literal::string("a'\\\0\n\r\x1a"))->toBe("'a\\'\\\\\\0\\n\\r\\Z'");
});
