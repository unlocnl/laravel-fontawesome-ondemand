# Font Awesome On-Demand for Laravel

Fetches Font Awesome 6 and 7 icons on demand, caches them disk-first, and renders them through a `<x-fa>` Blade component.

Icons come from two places. The free set is pulled unauthenticated from the `@fortawesome/fontawesome-free` npm package on jsDelivr; everything else comes from the official Font Awesome GraphQL API, whose `svgs` field is authenticated even for free icons. Out of the box, with no `FONTAWESOME_API_TOKEN`, the free set works and nothing else does. Set a token to unlock Pro families and styles.

The free package carries the `classic` family only. `sharp`, `duotone`, `sharp-duotone` and the `light` / `thin` / `semibold` styles need a Pro token.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Installation

```bash
composer require unloc/laravel-fontawesome-ondemand
php artisan vendor:publish --tag=fontawesome-config
```

This publishes `config/fontawesome.php`.

## Configuration

All keys live in `config/fontawesome.php`.

| Key | Description |
|-|-|
| `version` | Font Awesome release series, `6` or `7`. Used verbatim in the GraphQL `release(version: "{version}.x")` query and as the CDN package specifier `fontawesome-free@{version}`. Also namespaces the disk and cache keys. |
| `source` | Reads `FONTAWESOME_SOURCE`. `auto` (default) serves free icons from the CDN and sends everything else — plus any CDN miss — to the GraphQL API when a token is set, and inverts that order for `fontawesome:prefetch` (see [Commands](#commands)); `cdn` uses jsDelivr only; `api` uses the GraphQL API only. An unrecognized value throws. |
| `api_token` | Reads `FONTAWESOME_API_TOKEN`. Unlocks Pro families and styles, and lets `auto` fall through to the API for icons the free package lacks. The client exchanges it for a short-lived GraphQL token and caches that exchange. Without a token the API leg is skipped entirely. |
| `endpoint` | Font Awesome GraphQL endpoint. Override for testing/mocking. |
| `cdn_endpoint` | npm CDN root the free package is fetched from. Requests go to `{cdn_endpoint}/@fortawesome/fontawesome-free@{version}/svgs/{style}/{name}.svg`. |
| `defaults.family` | Default family (`classic`, `sharp`, `sharp-duotone`, `duotone`) applied when `<x-fa>`'s `family` attribute is omitted. Note: `brands` is a *style*, not a family. |
| `defaults.style` | Default style (`solid`, `regular`, `light`, `thin`, `semibold`, `duotone`, `brands`) applied when `<x-fa>`'s `variant` attribute is omitted. |
| `classes` | CSS classes merged into every rendered `<svg>` by default (e.g. sizing utility classes). Component/attribute classes are appended, not replaced. |
| `custom.path` | Directory the bundled filesystem source reads app-owned SVGs from. Style subfolders act as variants; root-level files answer any style. `null` disables it. See [Custom icons](#custom-icons). |
| `prefetch` | List of icons to always warm via `fontawesome:prefetch`. Each entry is a string (icon name, uses defaults) or an array `['name' => ..., 'family' => ..., 'style' => ...]`. |
| `scan_paths` | Extra directories (beyond `resource_path('views')`) that `fontawesome:prefetch` scans for `<x-fa>` usages. |
| `disk` | Filesystem disk (from `config/filesystems.php`) used for the on-disk SVG cache. |
| `path` | Root path within that disk where cached SVGs are stored. |
| `sanitize.strip_comments` | Strip HTML/XML comments from fetched SVG markup before caching/rendering. |
| `sanitize.remove_attributes` | List of attribute names to strip from the SVG markup wherever they appear, not just the root `<svg>` element (e.g. inline `style`). Supports wildcards like `data-*`. |
| `cache.store` | Cache store name for the persistent (non-disk) icon cache. `null` uses the app's default cache store (persistent + negative caching on); `false` disables the persistent cache; a string uses that specific store. |
| `cache.ttl` | TTL in seconds for successfully resolved icons in the persistent cache. `null` caches forever. |
| `cache.negative_ttl` | TTL in seconds for negative (icon-not-found) cache entries, so failed lookups aren't retried on every request. |
| `cache.prefix` | Key prefix used for all persistent cache entries, to avoid collisions with other cached data. |
| `on_error` | Behavior when an icon can't be resolved: `placeholder` (render a fallback SVG), `throw` (throw `IconNotFoundException`), or `empty` (render nothing). |
| `blaze.fold` | Register `<x-fa>` with Livewire Blaze for compile-time folding. Defaults to `true` and is ignored when Blaze isn't installed. See [Livewire Blaze](#livewire-blaze). |

## Usage

### Blade component

```blade
<x-fa name="gear" />
<x-fa name="heart" family="sharp" variant="solid" class="text-red-500" />
<x-fa name="github" /> {{-- brand auto-resolved --}}
```

`name` is required; `family` and `variant` fall back to `defaults.family`/`defaults.style` when omitted. Any other attributes (including `class` and `style`) are merged onto the rendered `<svg>` root element — default classes, existing SVG classes, and attribute classes are combined and deduplicated.

Brand icons (`github`, `square-github`, `gitlab`, `google`, `php`, `laravel`, ...) are recognized from the complete bundled brand list in `resources/brands.php` — all Font Awesome brand names, including the `square-*` variants — and resolved directly against the classic family with the `brands` style (`classic`/`brands`) in a single query, without needing to pass a family or style. Any name not in the list (e.g. a brand added in a newer release) still resolves via an automatic `classic`/`brands` fallback — just with one extra request on first fetch. The list is a first-fetch optimization only. Regenerate it against the latest release with:

```bash
composer update-brands          # 7.x by default
composer update-brands -- 6.x   # a specific release line
```

This pulls the current brand set from Font Awesome's public GraphQL metadata (no API token required).

### Custom icons

Icons the app owns — a logo, an in-house glyph set, SVGs uploaded through a CMS — resolve through the same `<x-fa>` component and need no API token.

Prefix the name with `c-` to resolve it against custom sources only:

```blade
<x-fa name="c-logo" class="w-8 h-8" />
<x-fa name="c-logo" variant="regular" />
```

A `c-` name never reaches the Font Awesome API. Without the prefix the icon is looked up at Font Awesome first and falls through to the custom sources only when that misses, which costs one API request the first time and keeps a stray custom file from shadowing an icon in the FA catalog.

#### From a folder

Drop SVGs in `custom.path` (`resources/fa-custom-icons` by default). A style subfolder holds that variant; a file at the root answers any style, so a single-variant icon needs no folder:

```
resources/fa-custom-icons/
├── logo.svg            # c-logo, any variant
├── mark.svg
└── regular/
    └── logo.svg        # c-logo variant="regular"
```

Family is ignored for custom icons.

#### From a database or an upload

Implement `CustomIconSource` and register it in a service provider. Sources registered this way are tried before the bundled filesystem source, so an uploaded icon overrides a shipped file of the same name:

```php
use Unloc\FontAwesome\Contracts\CustomIconSource;
use Unloc\FontAwesome\Facades\FontAwesome;

class UploadedIconSource implements CustomIconSource
{
    public function get(string $name, string $style): ?string
    {
        return Icon::query()
            ->where('name', $name)
            ->where(fn ($q) => $q->where('style', $style)->orWhereNull('style'))
            ->orderByRaw('style is null')
            ->value('svg');
    }
}

// AppServiceProvider::boot()
FontAwesome::addSource(new UploadedIconSource());
```

Return raw SVG markup or `null`; the package sanitizes and merges attributes for you.

#### Authoring

Custom SVGs are inlined verbatim next to Font Awesome ones and get the same `classes` merged onto the root, so they only look right if they are drawn on comparable terms.

1. **Draw on a 512-tall grid.** Font Awesome normalizes height, not width — `viewBox="0 0 512 512"` for `house`, `0 0 448 512` for `user`, `0 0 256 512` for `1`. Matching that height makes a custom icon sit at the same optical size in a line of text. The default `w-[1em] h-[1em]` is square, so a narrower glyph is centered in the box rather than stretched, exactly as Font Awesome's own narrow icons are.
2. **Always include `viewBox`; drop `width` and `height`.** Without a `viewBox` the sizing classes scale the viewport and not the artwork. Width and height attributes survive the merge and only add noise.
3. **Never hardcode a color, unless the color is the icon.** Omit `fill` or set `fill="currentColor"`. A literal `fill="#f00"` on a child path outranks the root's inherited `fill-current`, so `text-*` utilities will not recolor it. Stroke-drawn icons need `stroke="currentColor"` and `fill="none"`. The exception is a brand mark whose color is part of the identity, or a multi-color logo that cannot be a single path — there, per-path fills are the right answer, and that same cascade rule is what keeps a stray `text-*` from breaking the mark. Font Awesome's own `brands` style is not this case: `spotify` and `google` both ship as one `currentColor` path and follow the text color like any other glyph.
4. **Leave `class` off the root.** Classes are merged, not replaced — a root `class="w-6 h-6"` ends up alongside the default `w-[1em] h-[1em]` and stylesheet order decides the winner, not your markup. Pass per-usage classes on the component instead.
5. **Avoid `<defs>`, gradients, masks and clip paths, or prefix their ids.** The markup is inlined once per usage, so a shared `id="a"` collides across instances on the same page and references resolve to the first one.

A well-formed custom icon is a root tag and paths, nothing else:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M224 32 32 480h384L224 32z"/></svg>
```

Exports from design tools usually carry inline `style` attributes and editor metadata; `sanitize.remove_attributes` strips those on the way in.

#### Caching

Markup from a source is sanitized once on the way in, then cached — positively and negatively — in the same persistent cache as Font Awesome icons, so a database-backed source is queried once per icon. As with Font Awesome icons, **an edited icon takes effect after `php artisan fontawesome:clear`** (add `--views` when Blaze is installed).

Custom icons are not written to the on-disk store, because the folder or the database is already the durable copy and a third one in `storage/app` would go stale unnoticed. The consequence is that they have one cache tier instead of two: with `cache.store` set to `false`, a Font Awesome icon still comes off disk, while a custom icon goes back to its source on every render.

### Facade

```php
use Unloc\FontAwesome\Facades\FontAwesome;

FontAwesome::render('gear'); // Illuminate\Support\HtmlString, ready to echo
FontAwesome::get('gear');    // raw sanitized SVG markup, or null if not found
```

`render()` accepts `name`, `family`, `style` (the `<x-fa>` `variant` attribute maps to this parameter), plus an attributes array/`ComponentAttributeBag` for merging — it's what `<x-fa>` calls under the hood. `get()` returns the sanitized SVG string (or `null`) without attribute merging, useful for programmatic checks.

### Commands

```bash
php artisan fontawesome:prefetch
php artisan fontawesome:clear
php artisan fontawesome:clear --views
```

`fontawesome:prefetch` warms the cache for everything in `config('fontawesome.prefetch')` plus every static `<x-fa>` usage found by scanning `resource_path('views')` and any `scan_paths`. Usages with dynamic bindings (e.g. `:name="$icon"` or `{{ $var }}` interpolation) are skipped and counted, since the icon name can't be determined statically.

Warming runs in two phases — the requested icons, then brand fallbacks. On `api`, and on `auto` with a token, each phase is one batched GraphQL request, so several hundred icons cost two requests rather than several hundred; whatever the API doesn't answer falls back to the CDN. Without a token — `auto` with none, or `cdn` — every icon is its own CDN request, issued concurrently in waves of at most 25.

`fontawesome:clear` deletes cached SVGs from disk and flushes the persistent icon cache (scoped to the configured prefix — it never calls `Cache::flush()`). It leaves compiled Blade views untouched; pass `--views` to also run `view:clear`, which is what you want when Blaze has folded icons into them (see below).

> **Deploy tip:** run `fontawesome:prefetch` as its own deploy step to keep used icons up to date.

## Livewire Blaze

When [Livewire Blaze](https://github.com/livewire/blaze) is installed, this package registers `<x-fa>` for compile-time folding. A statically named usage compiles to the literal SVG in the parent template:

```blade
<x-fa name="gear" class="text-red-500" />
```

```php
<?php ob_start(); ?><svg viewBox="0 0 1 1" class="fill-current w-[1em] h-[1em] text-red-500"><path/></svg>
```

No component render, no cache lookup, no disk read at runtime. Usages with a dynamically bound `name`, `family`, or `variant` (e.g. `:name="$icon"`) are left alone by Blaze and resolve at runtime as usual.

Set `blaze.fold` to `false` to opt out. Because the registration targets the component file exactly, and exact-file matches always win in Blaze's path resolution, this config key — not `Blaze::optimize()->in(...)` on a parent directory — is how you turn it off.

An icon that can't be resolved is never folded. During a fold the package ignores `on_error` and throws, so Blaze falls back to emitting the unfolded component and your configured `on_error` applies at runtime instead. A transient API failure at build time therefore costs you the optimisation for that icon, not a placeholder baked into the page. (If you've turned on Blaze's own throw mode with `Blaze::throw()`, it rethrows instead of falling back — which is what you want while debugging.)

Run `fontawesome:prefetch` before `view:cache` so folding has a warm cache to read from. Without it, compilation still works, but it resolves icons over the network from inside the Blade compiler.

Compiled views are invalidated by the mtime of the component file, so clearing the icon cache or changing `fontawesome.*` config does not refresh already-folded output. That is what `fontawesome:clear --views` is for.

## Notes

- `<x-fa>` is registered as an anonymous component, which is what makes Blaze folding possible.
- Rendering happens server-side to plain SVG markup, so Inertia/Vue/React front ends can consume the output directly (e.g. via `v-html` or `dangerouslySetInnerHTML`) without a JS-side Font Awesome dependency.
- Resolution order is: in-memory request cache → persistent cache (`cache.store`) → disk cache (`disk`/`path`) → API. A successful API fetch is sanitized once and written back to both the disk cache and the persistent cache.

## License

MIT.
