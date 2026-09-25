<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Http\JsDelivrClient;
use Unloc\FontAwesome\Support\IconReference;

function cdn(): JsDelivrClient
{
    return new JsDelivrClient(app(HttpFactory::class), 'https://cdn.jsdelivr.net/npm', tries: 1);
}

$gear = fn () => new IconReference('gear', 'classic', 'solid', '7');

it('fetches an icon from the versioned free package without auth', function () use ($gear) {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response('<svg>gear</svg>')]);

    expect(cdn()->fetch($gear()))->toBe('<svg>gear</svg>');

    Http::assertSent(fn ($r) => $r->url() === 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7/svgs/solid/gear.svg'
        && ! $r->hasHeader('Authorization'));
});

it('uses the reference version in the package specifier', function () {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response('<svg/>')]);

    cdn()->fetch(new IconReference('gear', 'classic', 'solid', '6'));

    Http::assertSent(fn ($r) => str_contains($r->url(), 'fontawesome-free@6/svgs/solid/gear.svg'));
});

it('returns null on a 404', function () use ($gear) {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response("Couldn't find the requested file", 404)]);

    expect(cdn()->fetch($gear()))->toBeNull();
});

it('throws on any other failure status', function () use ($gear) {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response('', 500)]);

    expect(fn () => cdn()->fetch($gear()))->toThrow(IconFetchFailedException::class);
});

it('answers unsupported families and styles without a request', function () {
    Http::fake();

    $client = cdn();

    expect($client->fetch(new IconReference('gear', 'sharp', 'solid', '7')))->toBeNull()
        ->and($client->fetch(new IconReference('gear', 'classic', 'light', '7')))->toBeNull()
        ->and($client->fetch(new IconReference('gear', 'nonsense', 'solid', '7')))->toBeNull();

    Http::assertNothingSent();
});

it('accepts every free style', function () {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response('<svg/>')]);

    foreach (['solid', 'regular', 'brands'] as $style) {
        expect(cdn()->fetch(new IconReference('gear', 'classic', $style, '7')))->toBe('<svg/>');
    }

    Http::assertSentCount(3);
});

it('pools one request per unique reference and skips unsupported ones', function () {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response('<svg/>')]);

    $results = cdn()->fetchMany([
        new IconReference('gear', 'classic', 'solid', '7'),
        new IconReference('gear', 'classic', 'solid', '7'),
        new IconReference('github', 'classic', 'brands', '7'),
        new IconReference('gear', 'sharp', 'solid', '7'),
    ]);

    expect($results)->toBe([
        '7/sharp/solid/gear' => null,
        '7/classic/solid/gear' => '<svg/>',
        '7/classic/brands/github' => '<svg/>',
    ]);

    Http::assertSentCount(2);
});

it('keys a mixed batch by reference and maps each outcome', function () {
    Http::fake([
        'cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7/svgs/solid/gear.svg' => Http::response('<svg>gear</svg>'),
        'cdn.jsdelivr.net/*' => Http::response('missing', 404),
    ]);

    expect(cdn()->fetchMany([
        new IconReference('gear', 'classic', 'solid', '7'),
        new IconReference('ghost-of-an-icon', 'classic', 'solid', '7'),
    ]))->toBe([
        '7/classic/solid/gear' => '<svg>gear</svg>',
        '7/classic/solid/ghost-of-an-icon' => null,
    ]);
});

it('returns an empty array for no references', function () {
    Http::fake();

    expect(cdn()->fetchMany([]))->toBe([]);

    Http::assertNothingSent();
});

it('retries a server error inside the pool', function () use ($gear) {
    Http::fake(['cdn.jsdelivr.net/*' => Http::sequence()
        ->push('', 500)
        ->push('<svg>gear</svg>'),
    ]);

    $client = new JsDelivrClient(app(HttpFactory::class), 'https://cdn.jsdelivr.net/npm', tries: 3);

    expect($client->fetch($gear()))->toBe('<svg>gear</svg>');
    Http::assertSentCount(2);
});

it('does not retry a 404', function () use ($gear) {
    Http::fake(['cdn.jsdelivr.net/*' => Http::response('missing', 404)]);

    $client = new JsDelivrClient(app(HttpFactory::class), 'https://cdn.jsdelivr.net/npm', tries: 3);

    expect($client->fetch($gear()))->toBeNull();
    Http::assertSentCount(1);
});

it('resolves a batch larger than the concurrency cap, keyed correctly', function () {
    Http::fake(function ($request) {
        preg_match('#/svgs/solid/([^/]+)\.svg$#', $request->url(), $m);

        return Http::response("<svg>{$m[1]}</svg>");
    });

    $client = new JsDelivrClient(app(HttpFactory::class), 'https://cdn.jsdelivr.net/npm', concurrency: 3);

    $refs = array_map(fn (int $i) => new IconReference("icon-{$i}", 'classic', 'solid', '7'), range(1, 12));

    $results = $client->fetchMany($refs);

    expect($results)->toHaveCount(12)
        ->and($results['7/classic/solid/icon-1'])->toBe('<svg>icon-1</svg>')
        ->and($results['7/classic/solid/icon-12'])->toBe('<svg>icon-12</svg>');

    Http::assertSentCount(12);
});
