<?php

namespace Unloc\FontAwesome\Console;

use Illuminate\Console\Command;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconStore;

class ClearCommand extends Command
{
    protected $signature = 'fontawesome:clear {--views : Also clear compiled Blade views}';

    protected $description = 'Delete cached Font Awesome SVGs from disk and the persistent cache.';

    public function handle(IconStore $store, IconCache $cache): int
    {
        $store->clear();
        $cache->flush();

        $this->info('Font Awesome cache cleared.');

        if ($this->option('views')) {
            $this->call('view:clear');

            return self::SUCCESS;
        }

        if ($this->laravel->bound('blaze')) {
            $this->warn('Blaze folds icons into compiled views, which may now be stale. Re-run with --views, or run `php artisan view:clear`.');
        }

        return self::SUCCESS;
    }
}
