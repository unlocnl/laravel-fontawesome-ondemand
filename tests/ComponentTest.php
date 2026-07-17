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

it('uses the variant attribute to select the icon style and forwards style as a plain HTML attribute', function () {
    $sent = [];
    Http::fake(function ($request) use (&$sent) {
        $sent[] = json_decode($request->body(), true)['variables']['style'] ?? null;

        return Http::response(['data' => ['release' => ['icon' => ['svgs' => [['html' => '<svg viewBox="0 0 1 1"><path/></svg>']]]]]]);
    });

    $html = Blade::render('<x-fa name="heart" variant="regular" style="color:red" />');

    expect($sent)->toBe(['REGULAR'])
        ->and($html)->toContain('style="color:red"');
});
