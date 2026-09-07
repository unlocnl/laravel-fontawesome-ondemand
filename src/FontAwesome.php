<?php

namespace Unloc\FontAwesome;

use Closure;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Unloc\FontAwesome\Contracts\CustomIconSource;
use Unloc\FontAwesome\Exceptions\IconNotFoundException;
use Unloc\FontAwesome\Http\FontAwesomeClient;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconReference;
use Unloc\FontAwesome\Support\IconSourceChain;
use Unloc\FontAwesome\Support\IconStore;
use Unloc\FontAwesome\Support\SvgAttributeMerger;
use Unloc\FontAwesome\Support\SvgSanitizer;

class FontAwesome
{
    /** @var array<string,?string> */
    private array $memo = [];

    /** @param list<string> $brands */
    public function __construct(
        private IconStore $store,
        private IconCache $cache,
        private FontAwesomeClient $client,
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
    ) {}

    public function get(string $name, ?string $family = null, ?string $style = null): ?string
    {
        $name = strtolower(trim($name));

        if (str_starts_with($name, 'c-')) {
            return $this->custom(substr($name, 2), $style);
        }

        return $this->fontAwesome($name, $family, $style) ?? $this->custom($name, $style);
    }

    public function addSource(CustomIconSource $source): void
    {
        $this->sources->add($source);
    }

    public function render(string $name, ?string $family = null, ?string $style = null, ComponentAttributeBag|array|null $attributes = null): HtmlString
    {
        $svg = $this->get($name, $family, $style) ?? $this->handleMissing($name);

        return new HtmlString($this->merger->merge($svg, $this->defaultClasses, $attributes));
    }

    private function fontAwesome(string $name, ?string $family, ?string $style): ?string
    {
        $explicit = $family !== null || $style !== null;

        // Brand icons live in the classic family under the "brands" style.
        if (! $explicit && in_array($name, $this->brands, true)) {
            return $this->resolve(new IconReference($name, 'classic', 'brands'));
        }

        $ref = $this->reference($name, $family, $style);
        $svg = $this->resolve($ref);
        if ($svg !== null) {
            return $svg;
        }

        if (! $explicit && $ref->style !== 'brands') {
            return $this->resolve(new IconReference($name, 'classic', 'brands'));
        }

        return null;
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

        $svg = $this->store->get($ref);
        if ($svg !== null) {
            $this->cache->put($ref, $svg);

            return $this->memo[$memoKey] = $svg;
        }

        $html = $this->client->fetch($ref);
        if ($html === null) {
            $this->cache->putNegative($ref);

            return $this->memo[$memoKey] = null;
        }

        $svg = $this->sanitizer->sanitize($html);
        $this->store->put($ref, $svg);
        $this->cache->put($ref, $svg);

        return $this->memo[$memoKey] = $svg;
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

    private function reference(string $name, ?string $family, ?string $style): IconReference
    {
        return new IconReference(
            $name,
            strtolower(trim($family ?? $this->defaultFamily)),
            strtolower(trim($style ?? $this->defaultStyle)),
        );
    }
}
