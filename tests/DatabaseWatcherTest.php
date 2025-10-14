<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Pandtit\HealthCheck\Monitoring\DatabaseWatcher;

class DatabaseWatcherTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [\Pandtit\HealthCheck\HealthCheckServiceProvider::class];
    }

    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('health.check.database_slow_query', true);
        $this->app['config']->set('health.watchers.database.slow_query_threshold', 100);
        $this->app['config']->set('health.log.channels.slow_query', 'daily');
        $this->app['config']->set('health.name.session.user_id', 'uid'); // 假设有 uid 字段
    }

    /**
     * 测试慢查询会被记录日志
     */
    public function test_log_slow_query_records_log()
    {
        // 1. 断言：Log::channel()->warning() 会被调用一次
        Log::shouldReceive('channel->warning')
            ->once()
            ->with('Slow Query', \Mockery::type('array'));

        // 2. 创建 watcher
        $watcher = new DatabaseWatcher();

        // 3. 反射调用私有方法 logSlowQuery
        $method = new \ReflectionMethod($watcher, 'logSlowQuery');
        $method->setAccessible(true);

        // 4. 模拟一个查询对象
        $query = (object) [
            'sql' => 'select * from users where id = ?',
            'bindings' => [1],
        ];

        // 5. 触发慢查询日志（200ms > 阈值 100ms）
        $method->invoke($watcher, $query, 200);
    }
}