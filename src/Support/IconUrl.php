<?php

namespace Unloc\FontAwesome\Support;

final class IconUrl
{
    public function __construct(private string $prefix) {}

    public function for(IconReference $ref): string
    {
        $segments = array_map(
            rawurlencode(...),
            [$ref->version, $ref->family, $ref->style, $ref->name],
        );

        return '/' . trim($this->prefix, '/') . '/' . implode('/', $segments) . '.svg';
    }
}
