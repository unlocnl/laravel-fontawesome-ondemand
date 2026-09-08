<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.classes', 'w-4 h-4');
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg viewBox="0 0 1 1"><path/></svg>']]]]]])]);
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
        $sent[] = json_decode($request->body(), true)['variables']['style0'] ?? null;

        return Http::response(['data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg viewBox="0 0 1 1"><path/></svg>']]]]]]);
    });

    $html = Blade::render('<x-fa name="heart" variant="regular" style="color:red" />');

    expect($sent)->toBe(['REGULAR'])
        ->and($html)->toContain('style="color:red"');
});

it('renders a custom icon from the configured folder through the container', function () {
    $dir = sys_get_temp_dir() . '/fa-component-' . bin2hex(random_bytes(4));
    mkdir($dir . '/regular', 0777, true);
    file_put_contents($dir . '/logo.svg', '<svg viewBox="0 0 1 1"><path id="root"/></svg>');
    file_put_contents($dir . '/regular/logo.svg', '<svg viewBox="0 0 1 1"><path id="regular"/></svg>');
    config()->set('fontawesome.custom.path', $dir);
    Http::fake();

    expect(Blade::render('<x-fa name="c-logo" class="text-red-500" />'))
        ->toContain('id="root"')
        ->toContain('class="w-4 h-4 text-red-500"');
    expect(Blade::render('<x-fa name="c-logo" variant="regular" />'))->toContain('id="regular"');
    Http::assertNothingSent();
});

it('lets an app register its own icon source', function () {
    config()->set('fontawesome.custom.path', null);
    Http::fake();

    \Unloc\FontAwesome\Facades\FontAwesome::addSource(new class implements \Unloc\FontAwesome\Contracts\CustomIconSource
    {
        public function get(string $name, string $style): ?string
        {
            return $name === 'uploaded' ? '<svg viewBox="0 0 1 1"><path id="db"/></svg>' : null;
        }
    });

    expect(Blade::render('<x-fa name="c-uploaded" />'))->toContain('id="db"');
    Http::assertNothingSent();
});

it('treats blank family, variant and mode as absent', function () {
    expect(Blade::render('<x-fa name="gear" family="" />'))->toContain('<path')
        ->and(Blade::render('<x-fa name="gear" variant="" />'))->toContain('<path')
        ->and(Blade::render('<x-fa name="gear" mode="" />'))->toContain('<path');
});

it('keeps the brands fallback when family and variant are blank', function () {
    $sent = [];
    Http::fake(function ($request) use (&$sent) {
        $vars = json_decode($request->body(), true)['variables'];
        $sent[] = [$vars['family0'], $vars['style0']];

        return Http::response(['data' => ['release' => ['i0' => ['svgs' => []]]]]);
    });

    Blade::render('<x-fa name="github" family="" variant="" />');

    expect($sent)->toBe([['CLASSIC', 'BRANDS']]);
});
