<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

it('clears the disk tree and its own cache keys but not unrelated cache', function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.cache.store', 'array');
    Storage::disk('local')->put('fontawesome/7/classic/solid/gear.svg', '<svg/>');
    Cache::store('array')->put('unrelated', 'keep', 3600);

    $this->artisan('fontawesome:clear')->assertSuccessful();

    expect(Storage::disk('local')->exists('fontawesome/7/classic/solid/gear.svg'))->toBeFalse()
        ->and(Cache::store('array')->get('unrelated'))->toBe('keep');
});
