<?php

namespace Pandtit\HealthCheck\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Mockery;
use Orchestra\Testbench\TestCase;
use Pandtit\HealthCheck\HealthCheckServiceProvider;

class HealthCheckExtraEnabledTest extends HealthCheckEnabledTestBase
{

    protected function setUp(): void
    {
        parent::setUp();
        Mockery::close(); // 确保每次清理
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    protected function defineEnvironment($app, $configs = [])
    {
        $app['config']->set('health.enabled', true);
        $app['config']->set('health.allowed_ips', [
            '127.0.0.1',
            '192.168.1.0/24',
        ]);

        $app['config']->set('health.checks.database', true);
        $app['config']->set('health.checks.cache', true);
        $app['config']->set('health.checks.queue', true);

        if ($configs) {
            foreach ($configs as $config => $value) {

                $app['config']->set($config, $value);
            }
        }
    }

    // /**
    //  * 测试配置文件是否正确发布并合并
    //  */
    // public function test_config_file_is_published()
    // {
    //     $this->assertFileExists($this->app->basePath('config/health.php'));
    // }


    /**
     * 测试所有检查项启用且成功时返回 healthy
     */
    public function test_all_checks_enabled_and_pass()
    {
        $this->mockDBSuccess();
        $this->mockRedisSuccess();
        $this->mockQueueSuccess();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'errcode' => 0,
                'errmsg' => null,
                'status' => 'healthy',
                'data' => [
                    'database' => 'healthy',
                    'cache' => 'healthy',
                    'queue' => 'healthy'
                ],

            ]);
    }

    /**
     * 测试禁用数据库检查时跳过该检查
     */
    public function test_disable_database_check()
    {
        $this->defineEnvironment($this->app, ['health.checks.database' => false]);
        $this->mockRedisSuccess();
        $this->mockQueueSuccess();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'errcode' => 0,
                'errmsg' => null,
                'status' => 'healthy',
                'data' => [
                    'cache' => 'healthy',
                    'queue' => 'healthy'
                ],
            ]);
    }

    /**
     * 测试数据库检查失败时状态转为 unhealthy
     */
    public function test_database_check_failure_makes_unhealthy()
    {
        $this->mockDBFailure();
        $this->mockRedisSuccess();
        $this->mockQueueSuccess();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'unhealthy',
                'errmsg' => 'Database check failed',
                'data' => [
                    'database' => 'unhealthy',
                    'cache' => 'healthy',
                    'queue' => 'healthy'
                ],
                'errcode' => 0,
            ]);
    }

    /**
     * 测试 Redis 检查失败时状态转为 unhealthy
     */
    public function test_redis_check_failure_makes_unhealthy()
    {
        $this->mockDBSuccess();
        $this->mockRedisFailure();
        $this->mockQueueSuccess();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'unhealthy',
                'errmsg' => 'Cache check failed',
                'data' => [
                    'database' => 'healthy',
                    'cache' => 'unhealthy',
                    'queue' => 'healthy'
                ],
                'errcode' => 0,
            ]);
    }

    /**
     * 测试队列检查失败时状态转为 unhealthy
     */
    // public function test_queue_check_failure_makes_unhealthy()
    // {
    //     $this->mockDBSuccess();
    //     $this->mockRedisSuccess();
    //     $this->mockQueueFailure();

    //     $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
    //         ->get('/api/health');

    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'status' => 'unhealthy',
    //             'errmsg' => 'Queue check failed',
    //             'data' => [
    //                 'database' => 'healthy',
    //                 'cache' => 'healthy',
    //                 'queue' => 'unhealthy'
    //             ],
    //             'errcode' => 0,
    //         ]);
    // }

    /**
     * 测试多个检查失败时状态仍为 unhealthy
     */
    public function test_multiple_checks_failure_makes_unhealthy()
    {
        $this->mockDBFailure();
        $this->mockRedisFailure();
        $this->mockQueueFailure();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'unhealthy',
                // 'errmsg' => 'Database check failed',
                'data' => [
                    'database' => 'unhealthy',
                    'cache' => 'unhealthy',
                    'queue' => 'unhealthy'
                ],
                'errcode' => 0,
            ]);
    }



    /**
     * 模拟数据库检查成功
     */
    protected function mockDBSuccess()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->with('SELECT 1')->andReturn([['1']]);
    }

    /**
     * 模拟数据库检查失败
     */
    protected function mockDBFailure()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->with('SELECT 1')->andThrow(new \Exception('Database connection failed'));
    }

    /**
     * 模拟 Redis 检查成功
     */
    protected function mockRedisSuccess()
    {
        Redis::shouldReceive('connection')
            ->andReturnSelf()
            ->shouldReceive('ping')
            ->andReturn('PONG'); // Laravel Redis 通常返回 'PONG'
        // Redis::shouldReceive('command')->with('ping')->andReturn('PONG');
    }

    /**
     * 模拟 Redis 检查失败
     */
    protected function mockRedisFailure()
    {
        config(['queue.default' => 'redis']);

        Redis::shouldReceive('connection')
            ->with('ping')
            ->andThrow(new \Exception('Redis ping failed'));
        // Redis::shouldReceive('command')->with('ping')->andThrow(new \Exception('Redis ping failed'));
    }

    /**
     * 模拟队列检查成功（假设使用 Redis 驱动）
     */
    protected function mockQueueSuccess()
    {
        config(['queue.default' => 'redis']);
        // Redis::shouldReceive('command')->with('ping', [], 'redis')->andReturn('PONG');
        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturnSelf()
            ->shouldReceive('ping')
            ->andReturn('PONG'); // Laravel Redis 通常返回 'PONG'

    }

    /**
     * 模拟队列检查失败
     */
    protected function mockQueueFailure()
    {
        // 强制设置配置，避免环境差异
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'default', // 关键！
        ]);

        $connectionName = 'default';

        // 创建 mock 连接
        $mockConnection = Mockery::mock(\Illuminate\Redis\Connections\Connection::class);
        $mockConnection->shouldReceive('ping')
            ->andThrow(new \Exception('Simulated ping failure'));

        // Mock Redis门面
        Redis::shouldReceive('connection')
            ->with($connectionName)
            ->andReturn($mockConnection);

    }
}