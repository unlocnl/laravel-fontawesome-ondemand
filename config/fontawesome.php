<?php

return [
    'version' => 7,
    // Where SVG markup comes from.
    //   'auto' free icons (classic solid/regular/brands) from the jsDelivr CDN, everything
    //          else — and any CDN miss — from the GraphQL API when a token is configured.
    //          `fontawesome:prefetch` inverts this: with a token it warms through one
    //          batched GraphQL document rather than a request per icon
    //   'cdn'  jsDelivr only: no token, free icons only
    //   'api'  GraphQL only
    'source' => env('FONTAWESOME_SOURCE', 'auto'),
    // Unlocks Pro families and styles, and lets 'auto' fall through to the API for icons
    // the free package lacks. The GraphQL `svgs` field is authenticated even for free
    // icons, so without a token the API leg is skipped entirely.
    'api_token' => env('FONTAWESOME_API_TOKEN'),
    'endpoint' => 'https://api.fontawesome.com',
    'cdn_endpoint' => 'https://cdn.jsdelivr.net/npm',
    'defaults' => [
        'family' => 'classic',
        'style'  => 'solid',
    ],
    'classes' => 'fill-current w-[1em] h-[1em]',
    'custom' => [
        // Directory of app-owned SVGs read by the bundled filesystem source. Style
        // subfolders are variants; root-level files answer any style. null disables it.
        // Other sources (a database, an uploads table) are registered with
        // FontAwesome::addSource() and ignore this path.
        'path' => resource_path('fa-custom-icons'),
    ],
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
    'blaze' => [
        // Fold <x-fa> at compile time when livewire/blaze is installed, baking the
        // SVG into the compiled parent. Ignored without Blaze.
        'fold' => true,
    ],
];
