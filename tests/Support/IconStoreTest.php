<?php

use Illuminate\Support\Facades\Storage;
use Unloc\FontAwesome\Support\IconReference;
use Unloc\FontAwesome\Support\IconStore;

function store(): IconStore
{
    return new IconStore(Storage::disk('local'), 'fontawesome');
}

it('returns null on a miss and stores under the versioned path', function () {
    Storage::fake('local');
    $ref = new IconReference('gear', 'classic', 'solid', '7');

    expect(store()->get($ref))->toBeNull();

    store()->put($ref, '<svg/>');

    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/gear.svg');
    expect(store()->get($ref))->toBe('<svg/>');
    expect(store()->has($ref))->toBeTrue();
});

it('clears the whole tree', function () {
    Storage::fake('local');
    $ref = new IconReference('gear', 'classic', 'solid', '7');
    store()->put($ref, '<svg/>');

    store()->clear();

    expect(store()->has($ref))->toBeFalse();
});
