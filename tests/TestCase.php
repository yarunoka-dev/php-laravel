<?php

namespace Yarunoka\Laravel\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Yarunoka\Laravel\YarunokaServiceProvider;

/**
 * The base of the bridge integration tests. Boots a Laravel application
 * with testbench and registers YarunokaServiceProvider the way package
 * auto-discovery would.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [YarunokaServiceProvider::class];
    }

    /**
     * Pin the database to the in-memory sqlite connection testbench
     * ships, so the tests need no external database whatever the
     * surrounding environment sets.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->setConfig($app, 'database.default', 'testing');
    }

    /**
     * Touches the config type-safely from an environment definition
     * method (#[DefineEnvironment]).
     */
    protected function setConfig(Application $app, string $key, mixed $value): void
    {
        $app->make(Repository::class)->set($key, $value);
    }
}
