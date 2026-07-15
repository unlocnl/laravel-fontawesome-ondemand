<?php

use Illuminate\Support\Facades\Cache;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconReference;

$cacheFactory = fn (string|false|null $store = 'array'): IconCache => new IconCache(app('cache'), $store, null, 3600, 'fa_ondemand:', 7);
$ref = fn () => new IconReference('gear', 'classic', 'solid');

it('is disabled when no store is configured', function () use ($ref, $cacheFactory) {
    $c = $cacheFactory(false);
    $c->put($ref(), '<svg/>');
    expect($c->enabled())->toBeFalse()
        ->and($c->get($ref()))->toBeNull();
});

it('stores and retrieves an svg', function () use ($ref, $cacheFactory) {
    $c = $cacheFactory();
    $c->put($ref(), '<svg/>');
    expect($c->get($ref()))->toBe('<svg/>');
});

it('records and reports negative markers', function () use ($ref, $cacheFactory) {
    $c = $cacheFactory();
    $c->putNegative($ref());
    expect($c->get($ref()))->toBe(IconCache::NEGATIVE);
});

it('flushes only its own keys on a non-taggable store', function () use ($ref, $cacheFactory) {
    // file store is non-taggable, unlike array store in this Laravel version
    Cache::store('file')->clear();
    Cache::store('file')->put('unrelated', 'keep', 3600);
    $c = $cacheFactory('file');
    $c->put($ref(), '<svg/>');

    $c->flush();

    expect($c->get($ref()))->toBeNull()
        ->and(Cache::store('file')->get('unrelated'))->toBe('keep');
});
