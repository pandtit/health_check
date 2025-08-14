<?php

namespace Pandtit\HealthCheck\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Pandtit\HealthCheck\Middleware\CheckHealthIpWhitelist;

class CheckHealthIpWhitelistTest extends BaseTestCase
{
    protected CheckHealthIpWhitelist $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckHealthIpWhitelist();
    }

    /**
     * Setup the test environment.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // 设置默认配置
        $app['config']->set('health.allowed_ips', []);
        $app['config']->set('health.allow_proxy', false);
    }

    /**
     * 获取最小的 Laravel 应用所需服务提供者（兼容 5.8+）
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            // 如有自定义服务提供者可添加，否则可省略
        ];
    }

    /**
     * 辅助方法：模拟请求并执行中间件
     *
     * @param array $server
     * @param array $headers
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    private function handleRequest(array $server = [], array $headers = [])
    {
        $request = Request::create('/', 'GET', [], [], [], $server);

        // 手动设置 headers（Laravel 测试中 header() 方法依赖 Symfony Request）
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $this->middleware->handle($request, function ($req) {
            return response()->json(['status' => 'ok']);
        });
    }

    /**
     * @test
     */
    public function it_allows_allowed_ip_when_proxy_disabled()
    {
        config(['health.allowed_ips' => ['192.168.1.100']]);
        config(['health.allow_proxy' => false]);

        $server = ['REMOTE_ADDR' => '192.168.1.100'];
        $response = $this->handleRequest($server);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['status' => 'ok'], $data);
    }

    /**
     * @test
     */
    public function it_blocks_disallowed_ip_when_proxy_disabled()
    {
        config(['health.allowed_ips' => ['192.168.1.100']]);
        config(['health.allow_proxy' => false]);

        $server = ['REMOTE_ADDR' => '192.168.1.101'];
        $response = $this->handleRequest($server);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            '{"errcode":403,"errmsg":"Forbidden"}',
            $response->getContent()
        );
    }

    /**
     * @test
     */
    public function it_uses_x_real_ip_when_proxy_enabled_and_valid()
    {
        config(['health.allowed_ips' => ['203.0.113.1']]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '10.0.0.1'];
        $headers = ['X-Real-IP' => '203.0.113.1'];

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $this->assertEquals(['status' => 'ok'], json_decode($response->getContent(), true));
    }

    /**
     * @test
     */
    public function it_uses_first_public_ip_from_x_forwarded_for()
    {
        config(['health.allowed_ips' => ['203.0.113.5']]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '172.16.0.1'];
        $headers = ['X-Forwarded-For' => '203.0.113.5, 198.51.100.1, 10.0.0.5'];

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $this->assertEquals(['status' => 'ok'], json_decode($response->getContent(), true));
    }

    /**
     * @test
     */
    public function it_skips_private_ips_in_x_forwarded_for_and_finds_public_one()
    {
        config(['health.allowed_ips' => ['198.51.100.1']]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '10.0.0.1'];
        $headers = ['X-Forwarded-For' => '10.0.0.10, 198.51.100.1, 172.16.0.5'];

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['status' => 'ok'], json_decode($response->getContent(), true));
    }

    /**
     * @test
     */
    public function it_falls_back_to_remote_addr_when_no_valid_proxy_ip_found()
    {
        config(['health.allowed_ips' => ['192.168.1.200']]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '192.168.1.200'];
        $headers = ['X-Forwarded-For' => '10.0.0.1, 172.16.0.1']; // 全是私有 IP

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['status' => 'ok'], json_decode($response->getContent(), true));
    }

    /**
     * @test
     */
    public function it_blocks_request_when_all_ips_are_private_and_not_allowed()
    {
        config(['health.allowed_ips' => ['203.0.113.1']]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '10.0.0.1'];
        $headers = ['X-Forwarded-For' => '10.0.0.10, 192.168.1.100'];

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"errcode":403,"errmsg":"Forbidden"}', $response->getContent());
    }

    /**
     * @test
     */
    public function it_supports_other_proxy_headers_like_cf_connecting_ip()
    {
        config(['health.allowed_ips' => ['203.0.113.2']]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '10.0.0.1'];
        $headers = ['CF-Connecting-IP' => '203.0.113.2'];

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['status' => 'ok'], json_decode($response->getContent(), true));
    }

    /**
     * @test
     */
    public function it_blocks_when_no_ip_detected()
    {
        config(['health.allowed_ips' => ['1.1.1.1']]);
        config(['health.allow_proxy' => true]);

        $server = []; // 无 REMOTE_ADDR
        $headers = []; // 无代理头

        $response = $this->handleRequest($server, $headers);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"errcode":403,"errmsg":"Forbidden"}', $response->getContent());
    }

    /**
     * @test
     */
    public function it_blocks_when_allowed_ips_is_empty()
    {
        config(['health.allowed_ips' => []]);
        config(['health.allow_proxy' => true]);

        $server = ['REMOTE_ADDR' => '127.0.0.1'];
        $response = $this->handleRequest($server);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"errcode":403,"errmsg":"Forbidden"}', $response->getContent());
    }
}