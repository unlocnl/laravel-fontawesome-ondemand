<?php

use Unloc\FontAwesome\Contracts\CustomIconSource;
use Unloc\FontAwesome\Support\IconSourceChain;

function source(?string $svg): CustomIconSource
{
    return new class($svg) implements CustomIconSource
    {
        public function __construct(private ?string $svg) {}

        public function get(string $name, string $style): ?string
        {
            return $this->svg;
        }
    };
}

it('returns the first non-null hit', function () {
    $chain = new IconSourceChain([source(null), source('<svg>a</svg>'), source('<svg>b</svg>')]);

    expect($chain->get('logo', 'solid'))->toBe('<svg>a</svg>');
});

it('returns null when every source misses', function () {
    expect((new IconSourceChain([source(null), source(null)]))->get('logo', 'solid'))->toBeNull();
});

it('tries an added source ahead of the seeded ones', function () {
    $chain = new IconSourceChain([source('<svg>seeded</svg>')]);
    $chain->add(source('<svg>added</svg>'));

    expect($chain->get('logo', 'solid'))->toBe('<svg>added</svg>');
});
