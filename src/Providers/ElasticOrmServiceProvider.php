<?php

namespace SsWiking\ElasticOrm\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use SsWiking\ElasticOrm;

class ElasticOrmServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/elastic-orm.php', 'elastic-orm');

        $this->app->singleton(ElasticOrm\Contracts\Config::class, ElasticOrm\Config::class);
        $this->app->bind(ElasticOrm\Contracts\Builder::class, ElasticOrm\Builder::class);
        $this->app->bind('elastic-orm', ElasticOrm\Builder::class);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->bootCollectionMacros();

        if ($this->app->runningInConsole()) {
            // Publishing the config
            $this->publishes([
                __DIR__ . '/../../config/elastic-orm.php' => config_path('elastic-orm.php'),
            ], 'config');

            // Registering package commands.
            $this->commands([
                ElasticOrm\Commands\GenerateModelMeta::class,
            ]);
        }
    }

    /**
     * Defines collection macros
     *
     * @return void
     */
    private function bootCollectionMacros(): void
    {
        Collection::macro('recursive', function () {
            return $this->map(function ($value) {
                if (is_array($value)) {
                    return collect($value)->recursive();
                }

                return $value;
            });
        });
    }
}
