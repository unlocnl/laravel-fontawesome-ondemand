<?php

namespace Unloc\FontAwesome;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Unloc\FontAwesome\Console;
use Unloc\FontAwesome\Http\FontAwesomeClient;
use Unloc\FontAwesome\Support\FilesystemIconSource;
use Unloc\FontAwesome\Support\IconCache;
use Unloc\FontAwesome\Support\IconSourceChain;
use Unloc\FontAwesome\Support\IconStore;
use Unloc\FontAwesome\Support\SvgAttributeMerger;
use Unloc\FontAwesome\Support\SvgSanitizer;

class FontAwesomeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('fontawesome')
            ->hasConfigFile('fontawesome')
            ->hasViews('fontawesome')
            ->hasCommands([
                Console\PrefetchCommand::class,
                Console\ClearCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $config = fn (string $key, $default = null) => $this->app['config']->get("fontawesome.{$key}", $default);

        $this->app->singleton(IconStore::class, fn () => new IconStore(
            Storage::disk($config('disk', 'local')),
            $config('path', 'fontawesome'),
            $config('version', 7),
        ));

        $this->app->singleton(IconCache::class, fn ($app) => new IconCache(
            $app->make(CacheFactory::class),
            $config('cache.store'),
            $config('cache.ttl'),
            (int) $config('cache.negative_ttl', 3600),
            (string) $config('cache.prefix', 'fa_ondemand:'),
            $config('version', 7),
        ));

        $this->app->singleton(FontAwesomeClient::class, fn ($app) => new FontAwesomeClient(
            $app->make(HttpFactory::class),
            $app->make(CacheFactory::class)->store(),
            (string) $config('endpoint', 'https://api.fontawesome.com'),
            $config('api_token'),
            $config('version', 7),
        ));

        $this->app->singleton(IconSourceChain::class, fn () => new IconSourceChain([
            new FilesystemIconSource($config('custom.path')),
        ]));

        $this->app->singleton(SvgSanitizer::class, fn () => new SvgSanitizer(
            (bool) $config('sanitize.strip_comments', true),
            (array) $config('sanitize.remove_attributes', []),
        ));

        $this->app->singleton(SvgAttributeMerger::class, fn () => new SvgAttributeMerger());

        $this->app->singleton(FontAwesome::class, fn ($app) => new FontAwesome(
            store: $app->make(IconStore::class),
            cache: $app->make(IconCache::class),
            client: $app->make(FontAwesomeClient::class),
            sources: $app->make(IconSourceChain::class),
            sanitizer: $app->make(SvgSanitizer::class),
            merger: $app->make(SvgAttributeMerger::class),
            defaultFamily: (string) $config('defaults.family', 'classic'),
            defaultStyle: (string) $config('defaults.style', 'solid'),
            defaultClasses: (string) $config('classes', ''),
            brands: require __DIR__ . '/../resources/brands.php',
            onError: (string) $config('on_error', 'placeholder'),
            placeholderPath: __DIR__ . '/../resources/svg/placeholder.svg',
            isFolding: fn () => $this->app->bound('blaze') && $this->app->make('blaze')->isFolding(),
        ));
    }

    public function packageBooted(): void
    {
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components');

        if ($this->app['config']->get('fontawesome.blaze.fold', true) && $this->app->bound('blaze')) {
            $this->app->make('blaze')->optimize()->in(
                __DIR__ . '/../resources/views/components/fa.blade.php',
                fold: true,
            );
        }
    }
}
