<?php

namespace Unloc\FontAwesome\Contracts;

interface CustomIconSource
{
    /** @return string|null Raw, unsanitized SVG markup, or null when the source has no such icon. */
    public function get(string $name, string $style): ?string;
}
