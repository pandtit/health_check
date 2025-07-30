<?php
namespace Pandtit\HealthCheck;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class HealthCheckController
{
    public function __invoke(Request $request): JsonResponse
    {

        $status = [
            'errcode' => 0,
            'errmsg' => null,
            'status' => 'healthy',
            'data' => [],
            'at' => now()->toISOString(),
            'service' => config('app.name', 'Laravel'),
        ];

        // 检查数据库
        $enableDB = $request->input('enable_database', 1);

        if ($enableDB && config('health.checks.database')) {
            $dbStatus = $this->checkDatabase();
            $status['data']['database'] = $dbStatus['status'];
            if ($dbStatus['status'] === 'unhealthy') {
                $status['status'] = 'unhealthy';
                $status['errmsg'] = 'Database check failed';
            }
        }

        // 检查缓存（Redis）
        $enableCache = $request->input('enable_cache', 1);

        if ($enableCache && config('health.checks.cache')) {
            $cacheStatus = $this->checkCache();
            $status['data']['cache'] = $cacheStatus['status'];
            if ($cacheStatus['status'] === 'unhealthy') {
                $status['status'] = 'unhealthy';
                $status['errmsg'] = 'Cache check failed';
            }
        }

        // 检查队列
        $enableQueue = $request->input('enable_queue', 1);
        if ($enableQueue && config('health.checks.queue')) {
            $queueStatus = $this->checkQueue();
            $status['data']['queue'] = $queueStatus['status'];
            if ($queueStatus['status'] === 'unhealthy') {
                $status['status'] = 'unhealthy';
                $status['errmsg'] = 'Queue check failed';
            }
        }

        return response()->json($status, 200);
    }

    /**
     * 数据库连接检查（轻量级：SELECT 1）
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->select('SELECT 1');
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            Log::error('Database health check failed: ' . $e->getMessage());
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * 缓存（Redis）连接检查（使用PING）
     */
    protected function checkCache(): array
    {
        try {
            // Redis::command('ping');
            // 兼容5.8
            Redis::connection()->ping();
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            Log::error('Cache (Redis) health check failed: ' . $e->getMessage());
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * 队列连接检查（示例：检查队列是否存在）
     */
    protected function checkQueue(): array
    {
        try {
            // 根据队列驱动类型调整逻辑，例如：
            // 如果使用 Redis 作为队列驱动：
            $queueDriver = config('queue.default');
            if ($queueDriver === 'redis') {
                // 获取 Redis 连接名称（来自 queue 配置）
                $redisConnection = config('queue.connections.redis.connection') ?? 'default';
                $response = Redis::connection($redisConnection)->ping();
                // 通常返回 +PONG 或 true
                if (!$response) {
                    throw new Exception('Redis ping failed');
                }
            } elseif ($queueDriver === 'database') {
                DB::table('jobs')->count();
            }
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            Log::error('Queue health check failed: ' . $e->getMessage());
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }
}