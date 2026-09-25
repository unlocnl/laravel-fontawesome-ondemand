<?php

namespace Unloc\FontAwesome\Support;

final class IconReference
{
    public function __construct(
        public readonly string $name,
        public readonly string $family,
        public readonly string $style,
        public readonly string $version,
    ) {}

    public function key(): string
    {
        return "{$this->version}/{$this->family}/{$this->style}/{$this->name}";
    }
}
