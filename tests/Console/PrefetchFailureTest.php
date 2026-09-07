<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconReference;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.prefetch', ['gear']);
});

it('does not negative-cache when the batch request fails', function () {
    Http::fake(['api.fontawesome.com' => Http::response('nope', 500)]);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Storage::disk('local')->assertMissing('fontawesome/7/classic/solid/gear.svg');

    expect(app(IconCache::class)->get(new IconReference('gear', 'classic', 'solid')))->toBeNull();
});

it('negative-caches an icon the api reports as missing', function () {
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['i0' => null]]])]);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    expect(app(IconCache::class)->get(new IconReference('gear', 'classic', 'solid')))->toBe(IconCache::NEGATIVE);
});
