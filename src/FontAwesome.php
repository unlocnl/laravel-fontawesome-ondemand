<?php

namespace Unloc\FontAwesome;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Unloc\FontAwesome\Contracts\CustomIconSource;
use Unloc\FontAwesome\Contracts\IconFetcher;
use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Exceptions\IconNotFoundException;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconReference;
use Unloc\FontAwesome\Support\IconSourceChain;
use Unloc\FontAwesome\Support\IconStore;
use Unloc\FontAwesome\Support\IconUrl;
use Unloc\FontAwesome\Support\SvgAttributeMerger;
use Unloc\FontAwesome\Support\SvgSanitizer;

class FontAwesome
{
    /** The id given to the served file's root element, and the fragment <use> points at. */
    public const FRAGMENT = 'i';

    public const INLINE = 'inline';

    public const LINKED = 'linked';

    /** @var array<string,?string> */
    private array $memo = [];

    /** @param list<string> $brands */
    public function __construct(
        private IconStore $store,
        private IconCache $cache,
        private IconFetcher $client,
        private IconSourceChain $sources,
        private SvgSanitizer $sanitizer,
        private SvgAttributeMerger $merger,
        private string $defaultFamily,
        private string $defaultStyle,
        private string $defaultClasses,
        private array $brands,
        private string $onError,
        private string $placeholderPath,
        private ?Closure $isFolding = null,
        private ?IconFetcher $warmClient = null,
        private ?IconUrl $url = null,
        private string $defaultMode = self::INLINE,
    ) {}

    public function get(string $name, ?string $family = null, ?string $style = null): ?string
    {
        return $this->locate($name, $family, $style)['svg'] ?? null;
    }

    /** Resolves an exact reference, as encoded in an icon URL. */
    public function raw(string $name, string $family, string $style): ?string
    {
        $name = strtolower(trim($name));
        $family = strtolower(trim($family));
        $style = strtolower(trim($style));

        return $family === 'custom'
            ? $this->custom($name, $style)
            : $this->resolve(new IconReference($name, $family, $style));
    }

    /**
     * Resolves many icons in as few API requests as possible: one batched query for
     * the primary references, plus at most one more for the brands fallbacks.
     *
     * @param  iterable<array{name:string,family?:?string,style?:?string}>  $entries
     * @return list<bool> whether each entry resolved, in input order
     */
    public function warm(iterable $entries): array
    {
        $plans = [];
        foreach ($entries as $entry) {
            $plans[] = $this->plan(
                strtolower(trim($entry['name'])),
                $entry['family'] ?? null,
                $entry['style'] ?? null,
            );
        }

        $resolved = array_fill(0, count($plans), false);

        foreach (['primary', 'fallback'] as $phase) {
            $pending = [];
            foreach ($plans as $i => $plan) {
                $ref = $plan[$phase] ?? null;
                if ($resolved[$i] || $ref === null) {
                    continue;
                }

                $peeked = $this->peek($ref);
                if ($peeked === IconCache::NEGATIVE) {
                    continue;
                }
                if ($peeked !== null) {
                    $resolved[$i] = true;

                    continue;
                }

                $pending[$ref->key()] = $ref;
            }

            if ($pending === []) {
                continue;
            }

            try {
                $fetched = ($this->warmClient ?? $this->client)->fetchMany($pending);
            } catch (IconFetchFailedException $e) {
                Log::warning("[fontawesome] batch fetch failed: {$e->getMessage()}");

                continue;
            }

            foreach ($pending as $key => $ref) {
                $html = $fetched[$key] ?? null;
                $html === null ? $this->miss($ref) : $this->persist($ref, $html);
            }

            foreach ($plans as $i => $plan) {
                $ref = $plan[$phase] ?? null;
                if (! $resolved[$i] && $ref !== null && ($fetched[$ref->key()] ?? null) !== null) {
                    $resolved[$i] = true;
                }
            }
        }

        foreach ($plans as $i => $plan) {
            if (! $resolved[$i] && $plan['custom'] !== null) {
                $resolved[$i] = $this->custom($plan['custom'], $plan['style']) !== null;
            }
        }

        return $resolved;
    }

    public function addSource(CustomIconSource $source): void
    {
        $this->sources->add($source);
    }

    public function render(string $name, ?string $family = null, ?string $style = null, ComponentAttributeBag|array|null $attributes = null, ?string $mode = null): HtmlString
    {
        $mode = $this->mode($mode);
        $located = $this->locate($name, $family, $style);

        $svg = match (true) {
            $located === null => $this->handleMissing($name),
            $mode === self::LINKED && $this->url !== null => $this->linkedMarkup($located['svg'], $located['ref']),
            default => $located['svg'],
        };

        return new HtmlString($this->merger->merge($svg, $this->defaultClasses, $attributes));
    }

    private function mode(?string $mode): string
    {
        $mode = strtolower(trim(blank($mode) ? $this->defaultMode : $mode));

        return match ($mode) {
            self::INLINE, self::LINKED => $mode,
            default => throw new \InvalidArgumentException("Unknown Font Awesome render mode [{$mode}]."),
        };
    }

    /**
     * Mirrors get(), keeping the reference that answered so it can be turned into a URL.
     *
     * @return array{svg:string,ref:IconReference}|null
     */
    private function locate(string $name, ?string $family, ?string $style): ?array
    {
        $plan = $this->plan(strtolower(trim($name)), $family, $style);

        foreach (['primary', 'fallback'] as $phase) {
            $ref = $plan[$phase];
            if ($ref === null) {
                continue;
            }

            $svg = $this->resolve($ref);
            if ($svg !== null) {
                return ['svg' => $svg, 'ref' => $ref];
            }
        }

        if ($plan['custom'] === null) {
            return null;
        }

        $svg = $this->custom($plan['custom'], $plan['style']);

        return $svg === null
            ? null
            : ['svg' => $svg, 'ref' => $this->reference($plan['custom'], 'custom', $plan['style'])];
    }

    /**
     * The host element carries the icon's own viewBox: <use> scales the referenced
     * document into it, so a mismatch would crop or shrink the icon.
     */
    private function linkedMarkup(string $svg, IconReference $ref): string
    {
        $viewBox = preg_match('/viewBox="([^"]*)"/i', $svg, $m) ? $m[1] : '0 0 512 512';

        return '<svg viewBox="' . e($viewBox, false) . '">'
            . '<use href="' . e($this->url->for($ref), false) . '#' . self::FRAGMENT . '"/>'
            . '</svg>';
    }

    private function custom(string $name, ?string $style): ?string
    {
        $ref = $this->reference($name, 'custom', $style);
        $memoKey = $ref->key();
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $cached = $this->cache->get($ref);
        if ($cached === IconCache::NEGATIVE) {
            return $this->memo[$memoKey] = null;
        }
        if ($cached !== null) {
            return $this->memo[$memoKey] = $cached;
        }

        $raw = $this->sources->get($ref->name, $ref->style);
        if ($raw === null) {
            $this->cache->putNegative($ref);

            return $this->memo[$memoKey] = null;
        }

        $svg = $this->sanitizer->sanitize($raw);
        $this->cache->put($ref, $svg);

        return $this->memo[$memoKey] = $svg;
    }

    private function resolve(IconReference $ref): ?string
    {
        $peeked = $this->peek($ref);
        if ($peeked === IconCache::NEGATIVE) {
            return null;
        }
        if ($peeked !== null) {
            return $peeked;
        }

        try {
            $html = $this->client->fetch($ref);
        } catch (IconFetchFailedException $e) {
            // A transport failure is not evidence the icon is missing, so it is
            // memoized for this render but never written to the shared cache.
            Log::warning("[fontawesome] {$ref->key()}: {$e->getMessage()}");

            return $this->memo[$ref->key()] = null;
        }

        if ($html === null) {
            return $this->miss($ref);
        }

        return $this->persist($ref, $html);
    }

    /** @return string|null the svg, IconCache::NEGATIVE for a known miss, or null when unknown */
    private function peek(IconReference $ref): ?string
    {
        $memoKey = $ref->key();
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey] ?? IconCache::NEGATIVE;
        }

        $cached = $this->cache->get($ref);
        if ($cached === IconCache::NEGATIVE) {
            $this->memo[$memoKey] = null;

            return IconCache::NEGATIVE;
        }
        if ($cached !== null) {
            return $this->memo[$memoKey] = $cached;
        }

        $svg = $this->store->get($ref);
        if ($svg !== null) {
            $this->cache->put($ref, $svg);

            return $this->memo[$memoKey] = $svg;
        }

        return null;
    }

    private function persist(IconReference $ref, string $html): string
    {
        $svg = $this->sanitizer->sanitize($html);
        $this->store->put($ref, $svg);
        $this->cache->put($ref, $svg);

        return $this->memo[$ref->key()] = $svg;
    }

    private function miss(IconReference $ref): ?string
    {
        $this->cache->putNegative($ref);

        return $this->memo[$ref->key()] = null;
    }

    private function handleMissing(string $name): string
    {
        // A miss during a Blaze fold would be baked into the compiled view forever,
        // so throw and let Blaze fall back to the runtime path.
        $onError = $this->isFolding && ($this->isFolding)() ? 'throw' : $this->onError;

        return match ($onError) {
            'throw' => throw new IconNotFoundException("Font Awesome icon [{$name}] could not be resolved."),
            'empty' => '',
            default => (string) file_get_contents($this->placeholderPath),
        };
    }

    /**
     * The reference to try first, an optional brands fallback, and the custom-source
     * name to fall through to.
     *
     * @return array{primary:?IconReference,fallback:?IconReference,custom:?string,style:?string}
     */
    private function plan(string $name, ?string $family, ?string $style): array
    {
        $family = blank($family) ? null : $family;
        $style = blank($style) ? null : $style;

        if (str_starts_with($name, 'c-')) {
            return ['primary' => null, 'fallback' => null, 'custom' => substr($name, 2), 'style' => $style];
        }

        $explicit = $family !== null || $style !== null;

        if (! $explicit && in_array($name, $this->brands, true)) {
            return [
                'primary' => new IconReference($name, 'classic', 'brands'),
                'fallback' => null,
                'custom' => $name,
                'style' => $style,
            ];
        }

        $ref = $this->reference($name, $family, $style);

        return [
            'primary' => $ref,
            'fallback' => ! $explicit && $ref->style !== 'brands'
                ? new IconReference($name, 'classic', 'brands')
                : null,
            'custom' => $name,
            'style' => $style,
        ];
    }

    private function reference(string $name, ?string $family, ?string $style): IconReference
    {
        return new IconReference(
            $name,
            strtolower(trim($family ?? $this->defaultFamily)),
            strtolower(trim($style ?? $this->defaultStyle)),
        );
    }
}
