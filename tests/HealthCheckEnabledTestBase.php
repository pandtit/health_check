<?php

namespace Pandtit\HealthCheck\Tests;

use Orchestra\Testbench\TestCase;
use Pandtit\HealthCheck\HealthCheckServiceProvider;

abstract class HealthCheckEnabledTestBase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [HealthCheckServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('health.enabled', true);
        $app['config']->set('health.allowed_ips', [
            '127.0.0.1',
            '192.168.1.0/24',
        ]);

        $app['config']->set('health.checks.database', false);
        $app['config']->set('health.checks.cache', false);
        $app['config']->set('health.checks.queue', false);
    }
}