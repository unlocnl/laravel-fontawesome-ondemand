<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Unloc\FontAwesome\Exceptions\IconNotFoundException;
use Unloc\FontAwesome\FontAwesome;
use Unloc\FontAwesome\Http\FontAwesomeClient;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconSourceChain;
use Unloc\FontAwesome\Support\IconStore;
use Unloc\FontAwesome\Support\SvgAttributeMerger;
use Unloc\FontAwesome\Support\SvgSanitizer;

function manager(string $onError = 'placeholder', array $brands = [], ?Closure $isFolding = null, ?IconSourceChain $sources = null): FontAwesome
{
    Storage::fake('local');

    return new FontAwesome(
        store: new IconStore(Storage::disk('local'), 'fontawesome', 7),
        cache: new IconCache(app('cache'), 'array', null, 3600, 'fa_ondemand:', 7),
        client: new FontAwesomeClient(app(\Illuminate\Http\Client\Factory::class), app('cache')->store('array'), 'https://api.fontawesome.com', null, 7),
        sources: $sources ?? new IconSourceChain(),
        sanitizer: new SvgSanitizer(stripComments: true),
        merger: new SvgAttributeMerger(),
        defaultFamily: 'classic',
        defaultStyle: 'solid',
        defaultClasses: 'w-4 h-4',
        brands: $brands,
        onError: $onError,
        placeholderPath: __DIR__ . '/../resources/svg/placeholder.svg',
        isFolding: $isFolding,
    );
}

function fakeIcon(string $html): void
{
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['i0' => ['svgs' => [['html' => $html]]]]]])]);
}

function fakeMissing(): void
{
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['i0' => null]]])]);
}

it('fetches, sanitizes, stores on a miss', function () {
    fakeIcon('<svg viewBox="0 0 1 1"><!--!FA--><path/></svg>');
    $svg = manager()->get('gear');
    expect($svg)->toBe('<svg viewBox="0 0 1 1"><path/></svg>');
    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/gear.svg');
});

it('serves a disk hit without any http call', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fontawesome/7/classic/solid/gear.svg', '<svg>disk</svg>');
    Http::fake();
    $m = new FontAwesome(
        store: new IconStore(Storage::disk('local'), 'fontawesome', 7),
        cache: new IconCache(app('cache'), false, null, 3600, 'fa_ondemand:', 7),
        client: new FontAwesomeClient(app(\Illuminate\Http\Client\Factory::class), app('cache')->store('array'), 'https://api.fontawesome.com', null, 7),
        sources: new IconSourceChain(),
        sanitizer: new SvgSanitizer(),
        merger: new SvgAttributeMerger(),
        defaultFamily: 'classic', defaultStyle: 'solid', defaultClasses: '', brands: [],
        onError: 'placeholder', placeholderPath: __DIR__ . '/../resources/svg/placeholder.svg',
    );
    expect($m->get('gear'))->toBe('<svg>disk</svg>');
    Http::assertNothingSent();
});

it('short-circuits known brands with a single query', function () {
    $sent = [];
    Http::fake(function ($request) use (&$sent) {
        $vars = json_decode($request->body(), true)['variables'];
        $sent[] = "{$vars['family0']}/{$vars['style0']}";

        return Http::response(['data' => ['release' => ['i0' => ['svgs' => [['html' => '<svg>gh</svg>']]]]]]);
    });

    expect(manager(brands: ['github'])->get('github'))->toBe('<svg>gh</svg>');
    expect($sent)->toBe(['CLASSIC/BRANDS']); // single brands-style query; no default-style query fired
});

it('falls back to brands after a classic miss for unknown names', function () {
    $sent = [];
    Http::fake(function ($request) use (&$sent) {
        $style = json_decode($request->body(), true)['variables']['style0'];
        $sent[] = $style;
        $icon = $style === 'BRANDS' ? ['svgs' => [['html' => '<svg>x</svg>']]] : null;

        return Http::response(['data' => ['release' => ['i0' => $icon]]]);
    });

    expect(manager()->get('some-brand'))->toBe('<svg>x</svg>');
    expect($sent)->toBe(['SOLID', 'BRANDS']);
});

it('does not fall back when family is explicit', function () {
    fakeMissing();
    expect(manager()->get('gear', 'sharp', 'solid'))->toBeNull();
});

it('caches a negative result to avoid re-hitting the api', function () {
    fakeMissing();
    $m = manager();
    expect($m->get('nope', 'classic', 'solid'))->toBeNull();
    // second manager shares the array cache store within the app instance
    $m->get('nope', 'classic', 'solid');
    Http::assertSentCount(1);
});

it('renders a placeholder with merged classes on error', function () {
    fakeMissing();
    $out = manager('placeholder')->render('nope', 'classic', 'solid');
    expect($out)->toBeInstanceOf(HtmlString::class)
        ->and((string) $out)->toContain('<svg')->toContain('class="w-4 h-4"');
});

it('throws when on_error is throw', function () {
    fakeMissing();
    expect(fn () => manager('throw')->render('nope', 'classic', 'solid'))
        ->toThrow(IconNotFoundException::class);
});

it('renders empty when on_error is empty', function () {
    fakeMissing();
    expect((string) manager('empty')->render('nope', 'classic', 'solid'))->toBe('');
});

it('throws on a miss while folding, whatever on_error says', function (string $onError) {
    fakeMissing();
    expect(fn () => manager($onError, isFolding: fn () => true)->render('nope', 'classic', 'solid'))
        ->toThrow(IconNotFoundException::class);
})->with(['placeholder', 'empty']);

it('honours on_error once folding has finished', function () {
    fakeMissing();
    $folding = false;
    $manager = manager('empty', isFolding: function () use (&$folding) {
        return $folding;
    });

    expect((string) $manager->render('nope', 'classic', 'solid'))->toBe('');
});

it('merges default classes and bag class into a hit', function () {
    fakeIcon('<svg viewBox="0 0 1 1"><path/></svg>');
    $out = (string) manager()->render('gear', null, null, ['class' => 'text-red-500']);
    expect($out)->toContain('class="w-4 h-4 text-red-500"');
});

class RecordingIconSource implements \Unloc\FontAwesome\Contracts\CustomIconSource
{
    public int $calls = 0;

    /** @param array<string,string> $icons keyed by "style/name" or bare name */
    public function __construct(private array $icons) {}

    public function get(string $name, string $style): ?string
    {
        $this->calls++;

        return $this->icons["{$style}/{$name}"] ?? $this->icons[$name] ?? null;
    }
}

function customCache(): IconCache
{
    return new IconCache(app('cache'), 'array', null, 3600, 'fa_ondemand:', 7);
}

function customRef(string $name, string $style = 'solid'): \Unloc\FontAwesome\Support\IconReference
{
    return new \Unloc\FontAwesome\Support\IconReference($name, 'custom', $style);
}

it('resolves a c- prefixed icon from the chain without touching the api', function () {
    Http::fake();
    $svg = manager(sources: new IconSourceChain([new RecordingIconSource(['logo' => '<svg>logo</svg>'])]))->get('c-logo');

    expect($svg)->toBe('<svg>logo</svg>');
    Http::assertNothingSent();
});

it('resolves a c- prefixed icon per variant', function () {
    Http::fake();
    $m = manager(sources: new IconSourceChain([
        new RecordingIconSource(['logo' => '<svg>root</svg>', 'regular/logo' => '<svg>regular</svg>']),
    ]));

    expect($m->get('c-logo', null, 'regular'))->toBe('<svg>regular</svg>')
        ->and($m->get('c-logo'))->toBe('<svg>root</svg>');
});

it('never reaches the api for a missing c- prefixed icon', function () {
    Http::fake();
    expect(manager(sources: new IconSourceChain([new RecordingIconSource([])]))->get('c-nope'))->toBeNull();
    Http::assertNothingSent();
});

it('negative-caches a missing custom icon', function () {
    Http::fake();
    $source = new RecordingIconSource([]);
    manager(sources: new IconSourceChain([$source]))->get('c-nope');
    manager(sources: new IconSourceChain([$source]))->get('c-nope');

    expect(customCache()->get(customRef('nope')))->toBe(IconCache::NEGATIVE)
        ->and($source->calls)->toBe(1);
});

it('falls back to the chain only after font awesome misses', function () {
    fakeMissing();
    $svg = manager(sources: new IconSourceChain([new RecordingIconSource(['logo' => '<svg>logo</svg>'])]))->get('logo');

    expect($svg)->toBe('<svg>logo</svg>');
    Http::assertSentCount(2); // solid, then the brands fallback
});

it('does not consult the chain when font awesome has the icon', function () {
    fakeIcon('<svg>fa</svg>');
    $source = new RecordingIconSource(['gear' => '<svg>custom</svg>']);

    expect(manager(sources: new IconSourceChain([$source]))->get('gear'))->toBe('<svg>fa</svg>')
        ->and($source->calls)->toBe(0);
});

it('prefers a source added at runtime over the seeded chain', function () {
    Http::fake();
    $m = manager(sources: new IconSourceChain([new RecordingIconSource(['logo' => '<svg>seeded</svg>'])]));
    $m->addSource(new RecordingIconSource(['logo' => '<svg>added</svg>']));

    expect($m->get('c-logo'))->toBe('<svg>added</svg>');
});

it('sanitizes and merges attributes onto a custom icon', function () {
    Http::fake();
    $source = new RecordingIconSource(['logo' => '<svg viewBox="0 0 1 1"><!--!c--><path/></svg>']);
    $out = (string) manager(sources: new IconSourceChain([$source]))->render('c-logo', null, null, ['class' => 'text-red-500']);

    expect($out)->not->toContain('<!--')
        ->and($out)->toContain('class="w-4 h-4 text-red-500"');
});

it('memoizes a custom icon within a single request', function () {
    Http::fake();
    $source = new RecordingIconSource(['logo' => '<svg>logo</svg>']);
    $m = manager(sources: new IconSourceChain([$source]));
    $m->get('c-logo');
    $m->get('c-logo');

    expect($source->calls)->toBe(1)
        ->and(customCache()->get(customRef('logo')))->toBe('<svg>logo</svg>');
});
