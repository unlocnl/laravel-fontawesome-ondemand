<?php

use Illuminate\View\ComponentAttributeBag;
use Unloc\FontAwesome\Support\SvgAttributeMerger;

$merger = fn () => new SvgAttributeMerger();

it('injects default classes into a class-less svg', function () use ($merger) {
    $out = $merger()->merge('<svg viewBox="0 0 1 1"><path/></svg>', 'w-4 h-4');
    expect($out)->toContain('class="w-4 h-4"')->toContain('viewBox="0 0 1 1"');
});

it('merges and dedupes default, existing and bag classes', function () use ($merger) {
    $bag = new ComponentAttributeBag(['class' => 'text-red-500 w-4']);
    $out = $merger()->merge('<svg class="w-4"><path/></svg>', 'w-4 h-4', $bag);
    // order: default, existing, bag — deduped
    expect($out)->toContain('class="w-4 h-4 text-red-500"');
});

it('merges arbitrary bag attributes and lets the bag override', function () use ($merger) {
    $bag = new ComponentAttributeBag(['id' => 'x', 'aria-hidden' => 'true', 'viewBox' => '0 0 2 2']);
    $out = $merger()->merge('<svg viewBox="0 0 1 1"><path/></svg>', '', $bag);
    expect($out)->toContain('id="x"')
        ->toContain('aria-hidden="true"')
        ->toContain('viewBox="0 0 2 2"')
        ->not->toContain('viewBox="0 0 1 1"');
});

it('returns input unchanged when there is no svg tag', function () use ($merger) {
    expect($merger()->merge('', 'w-4'))->toBe('');
});
