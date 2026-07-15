<?php

namespace Unloc\FontAwesome\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Unloc\FontAwesome\FontAwesomeServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FontAwesomeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
