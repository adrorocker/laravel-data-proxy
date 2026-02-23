<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests;

use AdroSoftware\DataProxy\Laravel\DataProxyServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DataProxyServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'DataProxy' => \AdroSoftware\DataProxy\Laravel\DataProxyFacade::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
