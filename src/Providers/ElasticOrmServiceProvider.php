<?php

namespace SsWiking\ElasticOrm\Providers;

use Illuminate\Support\ServiceProvider;
use SsWiking\ElasticOrm;
use SsWiking\ElasticOrm\Contracts;

class ElasticOrmServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/elastic-orm.php', 'elastic-orm');

        $this->app->singleton(Contracts\Config::class, ElasticOrm\Config::class);
        $this->app->bind(Contracts\Builder::class, ElasticOrm\Builder::class);
        $this->app->bind('elastic-orm', ElasticOrm\Builder::class);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        // Publishing the config
        $this->publishes([
            __DIR__ . '/../../config/elastic-orm.php' => config_path('elastic-orm.php'),
        ], 'config');

        // Registering package commands.
        $this->commands([
            ElasticOrm\Commands\GenerateModelMeta::class,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function provides(): array
    {
        return [
            Contracts\Config::class,
            Contracts\Builder::class,
            ElasticOrm\Config::class,
            ElasticOrm\Builder::class,
        ];
    }
}
