<?php

namespace Pandtit\HealthCheck\Tests;

use Orchestra\Testbench\TestCase;
use Pandtit\HealthCheck\HealthCheckServiceProvider;

class HealthCheckDisabledTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [HealthCheckServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('health.enabled', false);
        $app['config']->set('health.allowed_ips', [
            '127.0.0.1',
            '192.168.1.0/24',
        ]);
    }

    public function test_health_route_returns_404_when_disabled()
    {
        $this->get('/api/health')->assertStatus(404);
    }
}