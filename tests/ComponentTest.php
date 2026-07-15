<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.classes', 'w-4 h-4');
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['icon' => ['svgs' => [['html' => '<svg viewBox="0 0 1 1"><path/></svg>']]]]]])]);
});

it('renders the x-fa component with merged classes and attributes', function () {
    $html = Blade::render('<x-fa name="gear" class="text-red-500" aria-hidden="true" />');
    expect($html)->toContain('<svg')
        ->toContain('class="w-4 h-4 text-red-500"')
        ->toContain('aria-hidden="true"');
});

it('resolves the facade to the manager', function () {
    expect(\Unloc\FontAwesome\Facades\FontAwesome::get('gear'))->toContain('<svg');
});
