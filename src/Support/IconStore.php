<?php

namespace Unloc\FontAwesome\Support;

use Illuminate\Contracts\Filesystem\Filesystem;

class IconStore
{
    public function __construct(
        private Filesystem $disk,
        private string $basePath,
        private int|string $version,
    ) {}

    public function get(IconReference $ref): ?string
    {
        $path = $this->path($ref);

        return $this->disk->exists($path) ? $this->disk->get($path) : null;
    }

    public function put(IconReference $ref, string $svg): void
    {
        $this->disk->put($this->path($ref), $svg);
    }

    public function has(IconReference $ref): bool
    {
        return $this->disk->exists($this->path($ref));
    }

    public function clear(): void
    {
        $this->disk->deleteDirectory($this->basePath);
    }

    private function path(IconReference $ref): string
    {
        return "{$this->basePath}/{$this->version}/{$ref->family}/{$ref->style}/{$ref->name}.svg";
    }
}
