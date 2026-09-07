<?php

namespace Unloc\FontAwesome\Http;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Unloc\FontAwesome\Contracts\IconFetcher;
use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Support\IconReference;

class JsDelivrClient implements IconFetcher
{
    // The free npm package ships the classic family only, under these styles.
    private const FREE_STYLES = ['solid', 'regular', 'brands'];

    public function __construct(
        private HttpFactory $http,
        private string $endpoint,
        private int|string $version,
        private int $tries = 3,
        private int $maxRetryDelay = 5000,
        private int $concurrency = 25,
    ) {}

    public static function supports(IconReference $ref): bool
    {
        return $ref->family === 'classic' && in_array($ref->style, self::FREE_STYLES, true);
    }

    public function fetch(IconReference $ref): ?string
    {
        return $this->fetchMany([$ref])[$ref->key()] ?? null;
    }

    /** Resolves every supported reference as one concurrent pool of static CDN requests. */
    public function fetchMany(iterable $refs): array
    {
        $results = [];
        $pending = [];

        foreach ($refs as $ref) {
            if (self::supports($ref)) {
                $pending[$ref->key()] = $ref;

                continue;
            }

            $results[$ref->key()] = null;
        }

        if ($pending === []) {
            return $results;
        }

        // Unbounded, a prefetch of several hundred icons would open that many sockets
        // at once; the pool is one request per icon, unlike the batched GraphQL leg.
        $responses = $this->http->pool(function (Pool $pool) use ($pending): void {
            foreach ($pending as $key => $ref) {
                $pool->as($key)
                    ->retry($this->tries, $this->retryDelay(), $this->retryWhen(), throw: false)
                    ->get($this->url($ref));
            }
        }, $this->concurrency);

        foreach ($pending as $key => $ref) {
            $results[$key] = $this->body($key, $responses[$key] ?? null);
        }

        return $results;
    }

    /** @throws IconFetchFailedException */
    private function body(string $key, mixed $response): ?string
    {
        if (! $response instanceof Response) {
            $message = $response instanceof \Throwable ? $response->getMessage() : 'no response';

            throw new IconFetchFailedException(
                "Request for {$key} to {$this->endpoint} failed: {$message}",
                previous: $response instanceof \Throwable ? $response : null,
            );
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new IconFetchFailedException("HTTP {$response->status()} from {$this->endpoint} for {$key}.");
        }

        return $response->body();
    }

    private function url(IconReference $ref): string
    {
        return "{$this->endpoint}/@fortawesome/fontawesome-free@{$this->version}/svgs/{$ref->style}/{$ref->name}.svg";
    }

    private function retryWhen(): \Closure
    {
        return static fn (\Throwable $e): bool => ! $e instanceof RequestException
            || $e->response->status() === 429
            || $e->response->serverError();
    }

    private function retryDelay(): \Closure
    {
        return function (int $attempt, \Throwable $e): int {
            $after = $e instanceof RequestException
                ? $this->retryAfter($e)
                : null;

            return min($after ?? (int) (100 * 2 ** ($attempt - 1)), $this->maxRetryDelay);
        };
    }

    private function retryAfter(RequestException $e): ?int
    {
        $header = $e->response->header('Retry-After');
        if ($header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return (int) ($header * 1000);
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, $timestamp - time()) * 1000;
    }
}
