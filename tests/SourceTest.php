<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Unloc\FontAwesome\Contracts\IconFetcher;
use Unloc\FontAwesome\Http\FontAwesomeClient;
use Unloc\FontAwesome\Http\IconFetcherChain;
use Unloc\FontAwesome\Http\JsDelivrClient;

function fetcher(string $source, ?string $token = null): IconFetcher
{
    config(['fontawesome.source' => $source, 'fontawesome.api_token' => $token]);
    app()->forgetInstance(IconFetcher::class);

    return app(IconFetcher::class);
}

it('binds the cdn alone when auto has no token', function () {
    expect(fetcher('auto'))->toBeInstanceOf(JsDelivrClient::class);
});

it('binds the chain when auto has a token', function () {
    expect(fetcher('auto', 'TOKEN'))->toBeInstanceOf(IconFetcherChain::class);
});

it('binds the cdn alone regardless of token', function () {
    expect(fetcher('cdn', 'TOKEN'))->toBeInstanceOf(JsDelivrClient::class);
});

it('binds the api alone regardless of token', function () {
    expect(fetcher('api'))->toBeInstanceOf(FontAwesomeClient::class);
});

it('rejects an unknown source', function () {
    expect(fn () => fetcher('carrier-pigeon'))->toThrow(InvalidArgumentException::class);
});

it('renders end to end from the cdn on auto without a token', function () {
    Storage::fake('local');
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response('<svg viewBox="0 0 1 1"><path/></svg>'),
        'api.fontawesome.com' => Http::response([], 500),
    ]);

    fetcher('auto');
    app()->forgetInstance(Unloc\FontAwesome\FontAwesome::class);

    expect(Blade::render('<x-fa name="house" />'))->toContain('<svg');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.fontawesome.com'));
    Http::assertSent(fn ($r) => $r->url() === 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7/svgs/solid/house.svg');

    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/house.svg');
});

it('falls through to the api for a pro style on auto', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response('missing', 404),
        'api.fontawesome.com/token' => Http::response(['access_token' => 'ACCESS', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response([
            'data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg>pro</svg>']]]]],
        ]),
    ]);

    expect(fetcher('auto', 'TOKEN')->fetchMany([
        new Unloc\FontAwesome\Support\IconReference('gear', 'sharp', 'light'),
    ]))->toBe(['sharp/light/gear' => '<svg>pro</svg>']);

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'cdn.jsdelivr.net'));
});

it('falls through to the api when the cdn misses a free path', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response('missing', 404),
        'api.fontawesome.com/token' => Http::response(['access_token' => 'ACCESS', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response([
            'data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg>pro</svg>']]]]],
        ]),
    ]);

    expect(fetcher('auto', 'TOKEN')->fetchMany([
        new Unloc\FontAwesome\Support\IconReference('gear', 'classic', 'solid'),
    ]))->toBe(['classic/solid/gear' => '<svg>pro</svg>']);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'cdn.jsdelivr.net'));
});

it('warms a pro install through one graphql request and never touches the cdn', function () {
    Storage::fake('local');
    config([
        'fontawesome.source' => 'auto',
        'fontawesome.api_token' => 'TOKEN',
        'fontawesome.prefetch' => ['house', 'heart', ['name' => 'gear', 'family' => 'sharp', 'style' => 'light']],
    ]);
    app()->forgetInstance(IconFetcher::class);
    app()->forgetInstance(Unloc\FontAwesome\FontAwesome::class);

    Http::fake([
        'api.fontawesome.com/token' => Http::response(['access_token' => 'ACCESS', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response(['data' => ['release' => [
            'i0' => ['svgs' => [['html' => '<svg>house</svg>']]],
            'i1' => ['svgs' => [['html' => '<svg>heart</svg>']]],
            'i2' => ['svgs' => [['html' => '<svg>gear</svg>']]],
        ]]]),
        'cdn.jsdelivr.net/*' => Http::response('<svg>cdn</svg>'),
    ]);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'cdn.jsdelivr.net'));
    Http::assertSentCount(2); // 1 token exchange + 1 batched graphql document
});

it('still renders through the cdn on the same pro install', function () {
    Storage::fake('local');
    config(['fontawesome.source' => 'auto', 'fontawesome.api_token' => 'TOKEN']);
    app()->forgetInstance(IconFetcher::class);
    app()->forgetInstance(Unloc\FontAwesome\FontAwesome::class);

    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response('<svg viewBox="0 0 1 1"><path/></svg>'),
        'api.fontawesome.com' => Http::response([], 500),
    ]);

    expect(Blade::render('<x-fa name="house" />'))->toContain('<svg');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.fontawesome.com'));
});

it('warms through the cdn when there is no token', function () {
    Storage::fake('local');
    config([
        'fontawesome.source' => 'auto',
        'fontawesome.api_token' => null,
        'fontawesome.prefetch' => ['house'],
    ]);
    app()->forgetInstance(IconFetcher::class);
    app()->forgetInstance(Unloc\FontAwesome\FontAwesome::class);

    Http::fake(['cdn.jsdelivr.net/*' => Http::response('<svg>house</svg>')]);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Http::assertSent(fn ($r) => $r->url() === 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7/svgs/solid/house.svg');
});
