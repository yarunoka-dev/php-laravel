<?php

namespace Yarunoka\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Yarunoka\Laravel\Internal\ConfigEnvironment;
use Yarunoka\Laravel\Internal\ScheduleCodec;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;

/**
 * The Laravel integration of Yarunoka. Binds YrnkEvaluator / YrnkParser
 * into the container, built from the config (timezone / calendar /
 * resolvers) as the environment.
 *
 * The bindings are scoped (per request) + If. Scoped, so the freshness
 * of what the resolvers answered rides on the DI scope; If, so a binding
 * the app makes itself always wins. The three date-set layers take an
 * app binding of the layer interface as an override of the config key.
 * The config is read when the environment is first derived within a
 * scope, not at registration — reading it in register() would run ahead
 * of later providers and of a test setting the config.
 */
class YarunokaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/yarunoka.php', 'yarunoka');

        // The internal wiring (not an override target for the app, so no If)
        $this->app->scoped(ConfigEnvironment::class);
        $this->app->scoped(ScheduleCodec::class);

        $this->app->scopedIf(YrnkEvaluator::class, static fn(Container $app): YrnkEvaluator => new YrnkEvaluator(
            $app->make(ConfigEnvironment::class)->calendar(),
            $app->make(ConfigEnvironment::class)->timezone(),
        ));

        $this->app->scopedIf(YrnkParser::class, static fn(Container $app): YrnkParser => new YrnkParser(
            $app->make(ConfigEnvironment::class)->resolverContainer(),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/yarunoka.php' => config_path('yarunoka.php'),
        ], 'yarunoka-config');
    }
}
