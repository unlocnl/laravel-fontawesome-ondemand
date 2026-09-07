<?php

namespace Unloc\FontAwesome\Http;

use Unloc\FontAwesome\Contracts\IconFetcher;
use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Support\IconReference;

final class IconFetcherChain implements IconFetcher
{
    /** @param list<IconFetcher> $fetchers */
    public function __construct(private array $fetchers) {}

    public function fetch(IconReference $ref): ?string
    {
        return $this->fetchMany([$ref])[$ref->key()] ?? null;
    }

    public function fetchMany(iterable $refs): array
    {
        $pending = [];
        foreach ($refs as $ref) {
            $pending[$ref->key()] = $ref;
        }

        $results = [];
        $last = array_key_last($this->fetchers);

        foreach ($this->fetchers as $i => $fetcher) {
            if ($pending === []) {
                break;
            }

            try {
                $fetched = $fetcher->fetchMany($pending);
            } catch (IconFetchFailedException $e) {
                // One leg being unreachable must not hide icons a later leg can still
                // serve; only the last leg's failure is the batch's failure.
                if ($i === $last) {
                    throw $e;
                }

                continue;
            }

            foreach ($fetched as $key => $svg) {
                if ($svg === null) {
                    continue;
                }

                $results[$key] = $svg;
                unset($pending[$key]);
            }
        }

        foreach ($pending as $key => $ref) {
            $results[$key] = null;
        }

        return $results;
    }
}
