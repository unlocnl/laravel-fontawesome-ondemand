<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.classes', 'w-4 h-4');
});

function fakeLinkedIcon(?string $viewBox = '0 0 448 512'): void
{
    $svgs = $viewBox === null ? [] : [['html' => '<svg viewBox="' . $viewBox . '"><path/></svg>']];

    Http::fake(fn () => Http::response(['data' => ['release' => ['i0' => ['svgs' => $svgs]]]]));
}

function linkedCustomIconPath(string $svg): string
{
    $dir = sys_get_temp_dir() . '/fa-linked-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/logo.svg', $svg);

    return $dir;
}

it('emits a use reference carrying the icon viewBox and the merged attributes', function () {
    fakeLinkedIcon();

    $html = Blade::render('<x-fa name="gear" mode="linked" class="text-red-500" />');

    expect($html)->toContain('<use href="/fontawesome/7/classic/solid/gear.svg#i"/>')
        ->toContain('viewBox="0 0 448 512"')
        ->toContain('class="w-4 h-4 text-red-500"')
        ->not->toContain('<path');
});

it('inlines the icon by default', function () {
    fakeLinkedIcon();

    expect(Blade::render('<x-fa name="gear" />'))->toContain('<path');
});

it('honors the config default and an explicit opt out', function () {
    fakeLinkedIcon();
    config()->set('fontawesome.mode', 'linked');

    expect(Blade::render('<x-fa name="star" />'))->toContain('<use href=');
    expect(Blade::render('<x-fa name="bell" mode="inline" />'))->toContain('<path');
});

it('rejects an unknown mode', function () {
    fakeLinkedIcon();

    expect(fn () => \Unloc\FontAwesome\Facades\FontAwesome::render('gear', mode: 'sprite'))
        ->toThrow(InvalidArgumentException::class, 'Unknown Font Awesome render mode [sprite].');
});

it('points a brand icon at the reference that actually answered', function () {
    Http::fake(function ($request) {
        $style = json_decode($request->body(), true)['variables']['style0'] ?? null;

        return $style === 'BRANDS'
            ? Http::response(['data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg viewBox="0 0 496 512"><path/></svg>']]]]]])
            : Http::response(['data' => ['release' => ['i0' => ['svgs' => []]]]]);
    });

    expect(Blade::render('<x-fa name="github" mode="linked" />'))
        ->toContain('/fontawesome/7/classic/brands/github.svg#i');
});

it('points a custom icon at the custom family', function () {
    fakeLinkedIcon(null);
    config()->set('fontawesome.custom.path', linkedCustomIconPath('<svg viewBox="0 0 24 24"><path/></svg>'));

    expect(Blade::render('<x-fa name="c-logo" mode="linked" />'))
        ->toContain('/fontawesome/7/custom/solid/logo.svg#i')
        ->toContain('viewBox="0 0 24 24"');
});

it('falls back to an inline placeholder when the icon is missing', function () {
    fakeLinkedIcon(null);

    expect(Blade::render('<x-fa name="nope" mode="linked" />'))->not->toContain('<use href=');
});

it('serves the icon with an id, a namespace and immutable caching', function () {
    fakeLinkedIcon();

    $response = $this->get('/fontawesome/7/classic/solid/gear.svg');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    expect($response->content())
        ->toContain('id="i"')
        ->toContain('xmlns="http://www.w3.org/2000/svg"')
        ->toContain('viewBox="0 0 448 512"');
});

it('replaces an id the source icon already carried', function () {
    fakeLinkedIcon(null);
    config()->set('fontawesome.custom.path', linkedCustomIconPath('<svg id="mine" viewBox="0 0 24 24"><path/></svg>'));

    $content = $this->get('/fontawesome/7/custom/solid/logo.svg')->assertOk()->content();

    expect($content)->toContain('id="i"')->not->toContain('id="mine"');
});

it('404s a stale version and an unresolvable icon', function () {
    fakeLinkedIcon(null);

    $this->get('/fontawesome/6/classic/solid/gear.svg')->assertNotFound();
    $this->get('/fontawesome/7/classic/solid/nope.svg')->assertNotFound();
});

it('drops the route and the use markup when the prefix is disabled', function () {
    fakeLinkedIcon();
    config()->set('fontawesome.linked.prefix', null);

    expect(Blade::render('<x-fa name="gear" mode="linked" />'))->toContain('<path');
});
