<?php

declare(strict_types=1);

/**
 * Regenerates resources/brands.php from the Font Awesome public GraphQL metadata.
 *
 * Brand icon *names* are public, so this needs no API token. It pages through
 * the FREE icon set for the given release and keeps every icon that has a
 * "brands" style.
 *
 * Usage:
 *   composer update-brands            # defaults to the 7.x release
 *   composer update-brands -- 6.x     # or a specific release line
 */

$version = $argv[1] ?? '7.x';
$endpoint = 'https://api.fontawesome.com';
$target = dirname(__DIR__) . '/resources/brands.php';

/**
 * @return array<string,mixed>
 */
function graphql(string $endpoint, string $query): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nUser-Agent: laravel-fontawesome-ondemand\r\n",
            'content' => json_encode(['query' => $query], JSON_THROW_ON_ERROR),
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($endpoint, false, $context);
    if ($raw === false) {
        fwrite(STDERR, "Request to {$endpoint} failed.\n");
        exit(1);
    }

    $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (! empty($json['errors'])) {
        fwrite(STDERR, 'GraphQL error: ' . json_encode($json['errors']) . "\n");
        exit(1);
    }

    return $json;
}

fwrite(STDERR, "Fetching brand icons for release {$version}...\n");

$names = [];
$page = 1;
do {
    $query = sprintf(
        '{release(version:"%s"){iconsPaginated(license:FREE,page:%d,pageSize:500){totalPageCount icons{id familyStylesByLicense{free{style}}}}}}',
        $version,
        $page
    );

    $paginated = graphql($endpoint, $query)['data']['release']['iconsPaginated'] ?? null;
    if ($paginated === null) {
        fwrite(STDERR, "No data for release {$version}.\n");
        exit(1);
    }

    foreach ($paginated['icons'] as $icon) {
        foreach ($icon['familyStylesByLicense']['free'] as $familyStyle) {
            if ($familyStyle['style'] === 'brands') {
                $names[$icon['id']] = true;
                break;
            }
        }
    }

    $totalPages = (int) $paginated['totalPageCount'];
    fwrite(STDERR, "  page {$page}/{$totalPages}\n");
    $page++;
} while ($page <= $totalPages);

$names = array_keys($names);
sort($names);

if ($names === []) {
    fwrite(STDERR, "Refusing to write an empty brand list.\n");
    exit(1);
}

$items = array_map(static fn (string $name): string => "'{$name}'", $names);
$body = implode("\n", array_map(
    static fn (string $line): string => '    ' . $line,
    explode("\n", wordwrap(implode(', ', $items), 96, "\n", false))
));

$count = count($names);
$php = <<<PHP
<?php

// Optimization only: the complete set of Font Awesome brand icon names, so
// `<x-fa name="github" />` (and square variants like `square-github`) resolves
// with a single GraphQL query (classic family, brands style) instead of a
// classic miss followed by a brands retry. Names absent here still resolve via
// the manager's classic-miss -> brands fallback (one extra request on first
// fetch), so a stale list degrades gracefully.
//
// Generated from the Font Awesome {$version} FREE brand set ({$count} icons).
// Regenerate with: composer update-brands
return [
{$body}
];

PHP;

file_put_contents($target, $php);
fwrite(STDERR, "Wrote {$count} brand names to {$target}\n");
