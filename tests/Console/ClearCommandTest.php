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

it('leaves compiled views alone but warns about them when blaze is installed', function () {
    $compiled = app('config')->get('view.compiled') . '/stale.php';
    file_put_contents($compiled, '<?php // folded');

    $this->artisan('fontawesome:clear')
        ->expectsOutputToContain('--views')
        ->assertSuccessful();

    expect(file_exists($compiled))->toBeTrue();
});

it('clears compiled views with the --views flag', function () {
    $compiled = app('config')->get('view.compiled') . '/stale.php';
    file_put_contents($compiled, '<?php // folded');

    $this->artisan('fontawesome:clear', ['--views' => true])->assertSuccessful();

    expect(file_exists($compiled))->toBeFalse();
});
