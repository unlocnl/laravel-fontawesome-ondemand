<?php

return [
    'version' => 7,
    // Required to fetch SVG markup: the Font Awesome GraphQL `svgs` field is
    // authenticated even for free icons. Without a token every icon falls back
    // to `on_error`. A free-tier token works for free icons; Pro token unlocks
    // Pro families/styles. Icon metadata (names/unicode) is public, but this
    // package needs the SVG.
    'api_token' => env('FONTAWESOME_API_TOKEN'),
    'endpoint' => 'https://api.fontawesome.com',
    'defaults' => [
        'family' => 'classic',
        'style'  => 'solid',
    ],
    'classes' => 'fill-current w-[1em] h-[1em]',
    'prefetch' => [],
    'scan_paths' => [],
    'disk' => 'local',
    'path' => 'fontawesome',
    'sanitize' => [
        'strip_comments'    => true,
        'remove_attributes' => [],
    ],
    'cache' => [
        // null = use the app's default cache store (persistent + negative caching on); false = disable; or a store name
        'store'        => null,
        'ttl'          => null,
        'negative_ttl' => 3600,
        'prefix'       => 'fa_ondemand:',
    ],
    'on_error' => 'placeholder',
];
