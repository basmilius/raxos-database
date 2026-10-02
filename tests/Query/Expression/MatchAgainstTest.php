<?php
declare(strict_types=1);

use Raxos\Database\Query\Expression\MatchAgainst;
use function Raxos\Database\Query\{column};
use function RaxosTests\Database\{compiledExpression};

covers(MatchAgainst::class);

it('escapes all indexed fields and binds the search text for every mode', function (bool $boolean, bool $expansion): void {
    [$sql, $params] = compiledExpression(new MatchAgainst([column('title'), column('body')], "x' OR 1=1", $boolean, $expansion));
    expect($sql)->toContain('match( `title` , `body` ) against (')->and($params)->toBe(["x' OR 1=1"]);
    expect(str_contains($sql, 'in boolean mode'))->toBe($boolean)->and(str_contains($sql, 'with query expansion'))->toBe($expansion);
    expect(compiledExpression(new MatchAgainst(column('title'), 'x'))[0])->toContain('match( `title` )');
})->with([[false, false], [true, false], [false, true], [true, true]]);
