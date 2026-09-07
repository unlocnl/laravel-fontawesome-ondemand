<?php

namespace Unloc\FontAwesome\Tests;

use Livewire\Blaze\BlazeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Unloc\FontAwesome\FontAwesomeServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BlazeServiceProvider::class, FontAwesomeServiceProvider::class];
    }

    // Folded icons are baked into compiled views, which outlive a single run.
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('view:clear');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('fontawesome.source', 'api');
    }
}
