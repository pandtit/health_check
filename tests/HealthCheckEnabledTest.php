<?php

namespace Pandtit\HealthCheck\Tests;

class HealthCheckEnabledTest extends HealthCheckEnabledTestBase
{
    public function test_health_route_returns_200_from_allowed_ip()
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/api/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'healthy']);
    }

    public function test_health_route_returns_403_from_blocked_ip()
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/api/health')
            ->assertStatus(403);
    }

    public function test_cidr_ip_range_allows_access()
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->get('/api/health')
            ->assertStatus(200);
    }
}