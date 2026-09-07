<?php

namespace Unloc\FontAwesome\Support;

use Unloc\FontAwesome\Contracts\CustomIconSource;

final class IconSourceChain implements CustomIconSource
{
    /** @param list<CustomIconSource> $sources */
    public function __construct(private array $sources = []) {}

    public function add(CustomIconSource $source): void
    {
        array_unshift($this->sources, $source);
    }

    public function get(string $name, string $style): ?string
    {
        foreach ($this->sources as $source) {
            $svg = $source->get($name, $style);
            if ($svg !== null) {
                return $svg;
            }
        }

        return null;
    }
}
