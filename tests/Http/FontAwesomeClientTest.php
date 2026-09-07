<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Unloc\FontAwesome\Http\FontAwesomeClient;
use Unloc\FontAwesome\Support\IconReference;

function client(?string $token = null): FontAwesomeClient
{
    return new FontAwesomeClient(app(HttpFactory::class), app('cache')->store('array'), 'https://api.fontawesome.com', $token, 7);
}

$ref = fn () => new IconReference('gear', 'classic', 'solid');

it('returns the svg html on success and sends no auth without a token', function () use ($ref) {
    Http::fake([
        'api.fontawesome.com' => Http::response([
            'data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg>gear</svg>']]]]],
        ]),
    ]);

    expect(client()->fetch($ref()))->toBe('<svg>gear</svg>');

    Http::assertSent(fn ($r) => $r->url() === 'https://api.fontawesome.com'
        && ! $r->hasHeader('Authorization'));
});

it('returns null when the icon is absent', function () use ($ref) {
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['i0' => null]]])]);
    expect(client()->fetch($ref()))->toBeNull();
});

it('exchanges the api token once and reuses it', function () use ($ref) {
    Http::fake([
        'api.fontawesome.com/token' => Http::response(['access_token' => 'ACCESS', 'expires_in' => 3600]),
        'api.fontawesome.com' => Http::response(['data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg/>']]]]]]),
    ]);

    $client = client('API_TOKEN');
    $client->fetch($ref());
    $client->fetch(new IconReference('user', 'classic', 'solid'));

    Http::assertSentCount(3); // 1 token + 2 graphql
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/token') && $r->hasHeader('Authorization', 'Bearer API_TOKEN'));
    Http::assertSent(fn ($r) => $r->url() === 'https://api.fontawesome.com' && $r->hasHeader('Authorization', 'Bearer ACCESS'));
});

it('throws on an unknown family', function () {
    Http::fake();
    expect(fn () => client()->fetch(new IconReference('gear', 'nonsense', 'solid')))
        ->toThrow(InvalidArgumentException::class);
});
