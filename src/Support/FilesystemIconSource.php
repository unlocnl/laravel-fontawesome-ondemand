<?php

namespace Unloc\FontAwesome\Support;

use Unloc\FontAwesome\Contracts\CustomIconSource;

final class FilesystemIconSource implements CustomIconSource
{
    public function __construct(private ?string $path) {}

    public function get(string $name, string $style): ?string
    {
        // The name reaches us straight from a Blade attribute, so keep it a leaf.
        if ($this->path === null || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name)) {
            return null;
        }

        foreach (["{$this->path}/{$style}/{$name}.svg", "{$this->path}/{$name}.svg"] as $file) {
            if (is_file($file)) {
                return (string) file_get_contents($file);
            }
        }

        return null;
    }
}
