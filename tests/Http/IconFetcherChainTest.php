<?php

use Unloc\FontAwesome\Contracts\IconFetcher;
use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Http\IconFetcherChain;
use Unloc\FontAwesome\Support\IconReference;

/** @param array<string,?string>|IconFetchFailedException $answers */
function leg(array|IconFetchFailedException $answers, ?array &$seen = null): IconFetcher
{
    return new class($answers, $seen) implements IconFetcher
    {
        public function __construct(private array|IconFetchFailedException $answers, private ?array &$seen) {}

        public function fetch(IconReference $ref): ?string
        {
            return $this->fetchMany([$ref])[$ref->key()] ?? null;
        }

        public function fetchMany(iterable $refs): array
        {
            $keys = [];
            foreach ($refs as $ref) {
                $keys[] = $ref->key();
            }

            $this->seen = $keys;

            if ($this->answers instanceof IconFetchFailedException) {
                throw $this->answers;
            }

            return array_combine($keys, array_map(fn (string $k) => $this->answers[$k] ?? null, $keys));
        }
    };
}

$gear = fn () => new IconReference('gear', 'classic', 'solid');
$swirl = fn () => new IconReference('swirl', 'sharp', 'light');

it('stops at the first leg that answers', function () use ($gear) {
    $second = null;

    $chain = new IconFetcherChain([
        leg(['classic/solid/gear' => '<svg>cdn</svg>']),
        leg(['classic/solid/gear' => '<svg>api</svg>'], $second),
    ]);

    expect($chain->fetch($gear()))->toBe('<svg>cdn</svg>')
        ->and($second)->toBeNull();
});

it('passes only the unanswered references to the next leg', function () use ($gear, $swirl) {
    $second = null;

    $chain = new IconFetcherChain([
        leg(['classic/solid/gear' => '<svg>cdn</svg>']),
        leg(['sharp/light/swirl' => '<svg>api</svg>'], $second),
    ]);

    expect($chain->fetchMany([$gear(), $swirl()]))->toBe([
        'classic/solid/gear' => '<svg>cdn</svg>',
        'sharp/light/swirl' => '<svg>api</svg>',
    ])->and($second)->toBe(['sharp/light/swirl']);
});

it('returns null for a reference no leg answers', function () use ($gear) {
    $chain = new IconFetcherChain([leg([]), leg([])]);

    expect($chain->fetch($gear()))->toBeNull();
});

it('lets a later leg cover an earlier one that failed', function () use ($gear) {
    $chain = new IconFetcherChain([
        leg(new IconFetchFailedException('cdn down')),
        leg(['classic/solid/gear' => '<svg>api</svg>']),
    ]);

    expect($chain->fetch($gear()))->toBe('<svg>api</svg>');
});

it('propagates a failure from the last leg', function () use ($gear) {
    $chain = new IconFetcherChain([
        leg([]),
        leg(new IconFetchFailedException('api down')),
    ]);

    expect(fn () => $chain->fetch($gear()))->toThrow(IconFetchFailedException::class, 'api down');
});

it('skips remaining legs once everything is answered', function () use ($gear) {
    $second = null;

    $chain = new IconFetcherChain([
        leg(['classic/solid/gear' => '<svg>cdn</svg>']),
        leg(new IconFetchFailedException('never reached'), $second),
    ]);

    expect($chain->fetch($gear()))->toBe('<svg>cdn</svg>')
        ->and($second)->toBeNull();
});

it('deduplicates references before dispatching', function () use ($gear) {
    $first = null;

    $chain = new IconFetcherChain([leg(['classic/solid/gear' => '<svg/>'], $first)]);

    $chain->fetchMany([$gear(), $gear()]);

    expect($first)->toBe(['classic/solid/gear']);
});
