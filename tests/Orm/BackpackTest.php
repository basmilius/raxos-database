<?php
declare(strict_types=1);

use Raxos\Database\Orm\Backpack;

covers(Backpack::class);

it('keeps null and false values present through array and object access', function (): void {
    $backpack = new Backpack(['empty' => null]);
    expect($backpack->hasValue('empty'))->toBeTrue()->and($backpack['empty'])->toBeNull()->and($backpack->missing)->toBeNull();
    $backpack['flag'] = false;
    $backpack->count = 0;
    expect($backpack->flag)->toBeFalse()->and($backpack['count'])->toBe(0);
    unset($backpack->flag);
    expect($backpack->hasValue('flag'))->toBeFalse();
    $backpack->replaceWith(['value' => 1]);
    expect($backpack->__debugInfo())->toBe(['value' => 1]);
    $backpack->clear();
    expect($backpack->__debugInfo())->toBe([]);
});
