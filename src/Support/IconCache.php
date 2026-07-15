<?php

namespace Unloc\FontAwesome\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

class IconCache
{
    public const NEGATIVE = '__fa_negative__';

    private const TAG = 'fontawesome';

    public function __construct(
        private CacheFactory $factory,
        private string|false|null $storeName,
        private ?int $ttl,
        private int $negativeTtl,
        private string $prefix,
        private int|string $version,
    ) {}

    public function enabled(): bool
    {
        return $this->storeName !== false;
    }

    public function get(IconReference $ref): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->repository()->get($this->key($ref));
    }

    public function put(IconReference $ref, string $svg): void
    {
        $this->write($this->key($ref), $svg, $this->ttl);
    }

    public function putNegative(IconReference $ref): void
    {
        $this->write($this->key($ref), self::NEGATIVE, $this->negativeTtl);
    }

    public function flush(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $store = $this->store();

        if ($this->supportsTags($store)) {
            $store->tags([self::TAG])->flush();

            return;
        }

        foreach ((array) $store->get($this->registryKey(), []) as $key) {
            $store->forget($key);
        }
        $store->forget($this->registryKey());
    }

    private function write(string $key, string $value, ?int $ttl): void
    {
        if (! $this->enabled()) {
            return;
        }

        $repository = $this->repository();
        $ttl === null ? $repository->forever($key, $value) : $repository->put($key, $value, $ttl);

        if (! $this->supportsTags($this->store())) {
            $this->registerKey($key);
        }
    }

    private function repository(): Repository
    {
        $store = $this->store();

        return $this->supportsTags($store) ? $store->tags([self::TAG]) : $store;
    }

    private function store(): Repository
    {
        return $this->factory->store($this->storeName === false ? null : $this->storeName);
    }

    private function supportsTags(Repository $store): bool
    {
        try {
            $store->tags([self::TAG]);

            return true;
        } catch (\BadMethodCallException) {
            return false;
        }
    }

    private function registerKey(string $key): void
    {
        $store = $this->store();
        $keys = (array) $store->get($this->registryKey(), []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $store->forever($this->registryKey(), $keys);
        }
    }

    private function key(IconReference $ref): string
    {
        return "{$this->prefix}{$this->version}:{$ref->key()}";
    }

    private function registryKey(): string
    {
        return "{$this->prefix}registry";
    }
}
