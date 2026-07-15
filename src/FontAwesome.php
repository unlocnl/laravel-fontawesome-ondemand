<?php

namespace Unloc\FontAwesome;

use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Unloc\FontAwesome\Exceptions\IconNotFoundException;
use Unloc\FontAwesome\Http\FontAwesomeClient;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconReference;
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
        private SvgSanitizer $sanitizer,
        private SvgAttributeMerger $merger,
        private string $defaultFamily,
        private string $defaultStyle,
        private string $defaultClasses,
        private array $brands,
        private string $onError,
        private string $placeholderPath,
    ) {}

    public function get(string $name, ?string $family = null, ?string $style = null): ?string
    {
        $name = strtolower(trim($name));
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

    public function render(string $name, ?string $family = null, ?string $style = null, ComponentAttributeBag|array|null $attributes = null): HtmlString
    {
        $svg = $this->get($name, $family, $style) ?? $this->handleMissing($name);

        return new HtmlString($this->merger->merge($svg, $this->defaultClasses, $attributes));
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
        return match ($this->onError) {
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
