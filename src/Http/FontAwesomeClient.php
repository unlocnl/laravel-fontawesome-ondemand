<?php

namespace Unloc\FontAwesome\Http;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Unloc\FontAwesome\Contracts\IconFetcher;
use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Support\IconReference;

class FontAwesomeClient implements IconFetcher
{
    // Font Awesome GraphQL `Family` enum. "brands" is NOT a family — brand
    // icons are the classic family under the "brands" style.
    private const FAMILY_MAP = [
        'chisel' => 'CHISEL',
        'classic' => 'CLASSIC',
        'duotone' => 'DUOTONE',
        'etch' => 'ETCH',
        'graphite' => 'GRAPHITE',
        'jelly' => 'JELLY',
        'jelly-duo' => 'JELLY_DUO',
        'jelly-fill' => 'JELLY_FILL',
        'mosaic' => 'MOSAIC',
        'notdog' => 'NOTDOG',
        'notdog-duo' => 'NOTDOG_DUO',
        'pixel' => 'PIXEL',
        'sharp' => 'SHARP',
        'sharp-duotone' => 'SHARP_DUOTONE',
        'slab' => 'SLAB',
        'slab-duo' => 'SLAB_DUO',
        'slab-press' => 'SLAB_PRESS',
        'slab-press-duo' => 'SLAB_PRESS_DUO',
        'thumbprint' => 'THUMBPRINT',
        'utility' => 'UTILITY',
        'utility-duo' => 'UTILITY_DUO',
        'utility-fill' => 'UTILITY_FILL',
        'vellum' => 'VELLUM',
        'whiteboard' => 'WHITEBOARD',
    ];

    // Font Awesome GraphQL `Style` enum.
    private const STYLE_MAP = [
        'solid' => 'SOLID',
        'regular' => 'REGULAR',
        'light' => 'LIGHT',
        'thin' => 'THIN',
        'semibold' => 'SEMIBOLD',
        'duotone' => 'DUOTONE',
        'brands' => 'BRANDS',
    ];

    public function __construct(
        private HttpFactory $http,
        private Repository $cache,
        private string $endpoint,
        private ?string $apiToken,
        private int|string $version,
        private int $tries = 3,
        private int $maxRetryDelay = 5000,
    ) {}

    public function fetch(IconReference $ref): ?string
    {
        return $this->fetchMany([$ref])[$ref->key()] ?? null;
    }

    /** Resolves every reference in a single aliased GraphQL document. */
    public function fetchMany(iterable $refs): array
    {
        $unique = [];
        foreach ($refs as $ref) {
            $unique[$ref->key()] = $ref;
        }

        if ($unique === []) {
            return [];
        }

        $aliases = [];
        $declarations = ['$version: String!'];
        $selections = [];
        $variables = ['version' => "{$this->version}.x"];
        $results = [];

        foreach ($unique as $key => $ref) {
            $family = self::FAMILY_MAP[$ref->family] ?? null;
            $style = self::STYLE_MAP[$ref->style] ?? null;

            // An unknown enum value fails the whole GraphQL document, not just its alias.
            if ($family === null || $style === null) {
                $results[$key] = null;

                continue;
            }

            $i = count($aliases);
            $alias = "i{$i}";
            $aliases[$alias] = $ref->key();

            $declarations[] = "\$name{$i}: String!";
            $declarations[] = "\$family{$i}: Family!";
            $declarations[] = "\$style{$i}: Style!";

            $selections[] = "{$alias}: icon(name: \$name{$i}) { svgs(filter: { familyStyles: [{ family: \$family{$i}, style: \$style{$i} }] }) { html } }";

            $variables["name{$i}"] = $ref->name;
            $variables["family{$i}"] = $family;
            $variables["style{$i}"] = $style;
        }

        if ($aliases === []) {
            return $results;
        }

        $query = sprintf(
            "query Icons(%s) {\n  release(version: \$version) {\n    %s\n  }\n}",
            implode(', ', $declarations),
            implode("\n    ", $selections),
        );

        $json = $this->post($this->authenticated(), [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (! empty($json['errors'])) {
            throw new IconFetchFailedException('GraphQL error: ' . json_encode($json['errors']));
        }

        $release = $json['data']['release'] ?? null;
        if (! is_array($release)) {
            throw new IconFetchFailedException("No data for release {$this->version}.x.");
        }

        foreach ($aliases as $alias => $key) {
            // A missing icon comes back as an explicit null alias with no errors;
            // an empty svgs list means the icon exists but not in that family/style.
            $results[$key] = $release[$alias]['svgs'][0]['html'] ?? null;
        }

        return $results;
    }

    private function authenticated(): PendingRequest
    {
        $request = $this->http->asJson()->acceptJson();

        if ($token = $this->accessToken()) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     *
     * @throws IconFetchFailedException
     */
    private function post(PendingRequest $request, array $payload, ?string $url = null): array
    {
        $url ??= $this->endpoint;

        try {
            $response = $request
                ->retry($this->tries, $this->retryDelay(), $this->retryWhen(), throw: false)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            throw new IconFetchFailedException("Request to {$url} failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new IconFetchFailedException("HTTP {$response->status()} from {$url}.");
        }

        return (array) $response->json();
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

    /** @throws IconFetchFailedException */
    private function accessToken(): ?string
    {
        if ($this->apiToken === null || $this->apiToken === '') {
            return null;
        }

        $key = 'fontawesome:access_token:' . md5($this->apiToken);
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }

        $json = $this->post($this->http->withToken($this->apiToken)->asJson(), [], "{$this->endpoint}/token");

        $token = $json['access_token'] ?? null;
        if (! $token) {
            throw new IconFetchFailedException('Token exchange returned no access_token.');
        }

        $expires = (int) ($json['expires_in'] ?? $json['expires_within_seconds'] ?? 3600);
        $this->cache->put($key, $token, max(60, $expires - 60));

        return $token;
    }
}
