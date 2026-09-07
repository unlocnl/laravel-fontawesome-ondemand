<?php

namespace Unloc\FontAwesome\Support;

final class IconUrl
{
    public function __construct(
        private string $prefix,
        private int|string $version,
    ) {}

    public function for(IconReference $ref): string
    {
        $segments = array_map(
            rawurlencode(...),
            [$this->version, $ref->family, $ref->style, $ref->name],
        );

        return '/' . trim($this->prefix, '/') . '/' . implode('/', $segments) . '.svg';
    }
}
