<?php

namespace Unloc\FontAwesome\Contracts;

use Unloc\FontAwesome\Exceptions\IconFetchFailedException;
use Unloc\FontAwesome\Support\IconReference;

interface IconFetcher
{
    /**
     * @throws IconFetchFailedException when the request could not be completed;
     *                                  a null return means the icon does not exist.
     */
    public function fetch(IconReference $ref): ?string;

    /**
     * @param  iterable<IconReference>  $refs
     * @return array<string,?string> keyed by IconReference::key(); null means the icon does not exist
     *
     * @throws IconFetchFailedException
     */
    public function fetchMany(iterable $refs): array;
}
