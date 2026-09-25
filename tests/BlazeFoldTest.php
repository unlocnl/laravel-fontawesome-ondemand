<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Unloc\FontAwesome\FontAwesomeServiceProvider;

function componentPath(): string
{
    return realpath(__DIR__ . '/../resources/views/components/fa.blade.php');
}

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.classes', 'w-4 h-4');
});

// Http::fake() appends stubs and the first match wins, so each test registers its own.
function fakeHit(): void
{
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['r0' => ['i0' => ['svgs' => [['html' => '<svg viewBox="0 0 1 1"><path/></svg>']]]]]])]);
}

function fakeMiss(): void
{
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['r0' => ['i0' => null]]])]);
}

it('folds a statically named icon into the compiled template', function () {
    fakeHit();

    $compiled = Blade::compileString('<x-fa name="gear" class="text-red-500" />');

    expect($compiled)->toContain('<svg viewBox="0 0 1 1" overflow="visible"')
        ->toContain('class="w-4 h-4 text-red-500"')
        ->not->toContain('pushData');
});

it('folds a version override', function () {
    fakeHit();
    config()->set('fontawesome.versions', [6]);

    expect(Blade::compileString('<x-fa name="gear" version="6" />'))
        ->toContain('<svg viewBox="0 0 1 1"')
        ->not->toContain('pushData');
    Storage::disk('local')->assertExists('fontawesome/6/classic/solid/gear.svg');
});

it('leaves a dynamically named icon to resolve at runtime', function () {
    fakeHit();

    $compiled = Blade::compileString('<x-fa :name="$icon" />');

    expect($compiled)->not->toContain('<svg')
        ->and($compiled)->toContain('pushData');
});

it('does not bake an on_error result into the compiled template', function () {
    config()->set('fontawesome.on_error', 'placeholder');
    fakeMiss();

    $compiled = Blade::compileString('<x-fa name="does-not-exist" />');

    expect($compiled)->not->toContain('<svg')
        ->and($compiled)->toContain('pushData');
});

it('registers the component with blaze by default', function () {
    expect(app('blaze')->optimize()->shouldFold(componentPath()))->toBeTrue();
});

it('skips registration when the config is off', function () {
    config()->set('fontawesome.blaze.fold', false);
    app('blaze')->optimize()->clear();

    (new FontAwesomeServiceProvider(app()))->packageBooted();

    expect(app('blaze')->optimize()->shouldFold(componentPath()))->toBeFalse();
});
