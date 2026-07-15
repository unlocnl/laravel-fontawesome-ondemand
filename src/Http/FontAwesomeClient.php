<?php

namespace Unloc\FontAwesome\Http;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Unloc\FontAwesome\Support\IconReference;

class FontAwesomeClient
{
    // Font Awesome GraphQL `Family` enum. "brands" is NOT a family — brand
    // icons are the classic family under the "brands" style.
    private const FAMILY_MAP = [
        'classic' => 'CLASSIC',
        'sharp' => 'SHARP',
        'duotone' => 'DUOTONE',
        'sharp-duotone' => 'SHARP_DUOTONE',
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
    ) {}

    public function fetch(IconReference $ref): ?string
    {
        $variables = [
            'version' => "{$this->version}.x",
            'name' => $ref->name,
            'family' => $this->enum(self::FAMILY_MAP, $ref->family, 'family'),
            'style' => $this->enum(self::STYLE_MAP, $ref->style, 'style'),
        ];

        try {
            $request = $this->http->asJson()->acceptJson();
            if ($token = $this->accessToken()) {
                $request = $request->withToken($token);
            }
            $response = $request->post($this->endpoint, [
                'query' => $this->query(),
                'variables' => $variables,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[fontawesome] request failed: ' . $e->getMessage());

            return null;
        }

        if ($response->failed()) {
            Log::warning("[fontawesome] HTTP {$response->status()} fetching {$ref->key()}");

            return null;
        }

        $json = $response->json();
        if (! empty($json['errors'])) {
            Log::warning("[fontawesome] GraphQL error for {$ref->key()}: " . json_encode($json['errors']));

            return null;
        }

        return $json['data']['release']['icon']['svgs'][0]['html'] ?? null;
    }

    private function accessToken(): ?string
    {
        if ($this->apiToken === null || $this->apiToken === '') {
            return null;
        }

        $key = 'fontawesome:access_token:' . md5($this->apiToken);
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }

        try {
            $response = $this->http->withToken($this->apiToken)->asJson()->post("{$this->endpoint}/token");
        } catch (\Throwable $e) {
            Log::warning('[fontawesome] token exchange failed: ' . $e->getMessage());

            return null;
        }

        if ($response->failed()) {
            Log::warning("[fontawesome] token exchange failed: HTTP {$response->status()}");

            return null;
        }

        $token = $response->json('access_token');
        if (! $token) {
            return null;
        }

        $expires = (int) ($response->json('expires_in') ?? $response->json('expires_within_seconds') ?? 3600);
        $this->cache->put($key, $token, max(60, $expires - 60));

        return $token;
    }

    /** @param array<string,string> $map */
    private function enum(array $map, string $value, string $label): string
    {
        return $map[$value] ?? throw new \InvalidArgumentException("Unknown Font Awesome {$label} [{$value}].");
    }

    private function query(): string
    {
        return <<<'GQL'
        query Icon($version: String!, $name: String!, $family: Family!, $style: Style!) {
          release(version: $version) {
            icon(name: $name) {
              id
              svgs(filter: { familyStyles: [{ family: $family, style: $style }] }) {
                html
                familyStyle { family style }
              }
            }
          }
        }
        GQL;
    }
}
