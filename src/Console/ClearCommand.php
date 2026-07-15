<?php

namespace Unloc\FontAwesome\Console;

use Illuminate\Console\Command;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconStore;

class ClearCommand extends Command
{
    protected $signature = 'fontawesome:clear';

    protected $description = 'Delete cached Font Awesome SVGs from disk and the persistent cache.';

    public function handle(IconStore $store, IconCache $cache): int
    {
        $store->clear();
        $cache->flush();

        $this->info('Font Awesome cache cleared.');

        return self::SUCCESS;
    }
}
