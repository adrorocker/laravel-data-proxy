<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Laravel;

use AdroSoftware\DataProxy\Config;
use AdroSoftware\DataProxy\DataProxy;
use Illuminate\Support\ServiceProvider;

class DataProxyServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/dataproxy.php', 'dataproxy');

        $this->app->singleton(Config::class, function ($app) {
            return new Config($app['config']->get('dataproxy', []));
        });

        $this->app->singleton(DataProxy::class, function ($app) {
            return new DataProxy($app->make(Config::class));
        });

        $this->app->alias(DataProxy::class, 'dataproxy');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/dataproxy.php' => config_path('dataproxy.php'),
            ], 'dataproxy-config');
        }
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            Config::class,
            DataProxy::class,
            'dataproxy',
        ];
    }
}
