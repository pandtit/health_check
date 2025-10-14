<?php

namespace Pandtit\HealthCheck\Monitoring;

use Illuminate\Support\Facades\DB;
use Pandtit\HealthCheck\Contracts\WatcherContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class DatabaseWatcher implements WatcherContract
{

    public function register(): void
    {
        if (!config('health.checks.database_slow_query'))
            return;

        $this->setupSlowQueryListener();
    }




    private function setupSlowQueryListener()
    {
        $slowQueryThreshold = config('health.watchers.database.slow_query_threshold', 100);//env('SLOW_QUERY_THRESHOLD', 100); // 默认100ms

        if ($slowQueryThreshold > 0) {
            try {
                DB::listen(function ($query) use ($slowQueryThreshold) {
                    $time = $query->time;
                    if ($time > $slowQueryThreshold) {
                        // 判断频次
                        if (!$this->allowLogSlowQuery()) {
                            return;
                        }

                        // 熔断，防止日志爆了
                        if ($this->shouldBreakCircuit()) {
                            return;
                        }

                        try {
                            $this->logSlowQuery($query, $time);

                        } catch (\Throwable $th) {
                            //throw $th;
                            Log::error('记录慢查询日志异常', ['msg' => $th->getMessage(), 'line' => $th->getLine()]);
                        }
                    }
                });
            } catch (\Throwable $th) {
                //throw $th;
                Log::error('监听慢查询失败', ['msg' => $th->getMessage()]);
            }

        }
    }

    private function getLogChannelForSlowQuery()
    {
        return config('health.log.channels.slow_query', 'daily');
    }

    /**
     * 记录慢查询
     * @param mixed $query
     * @param mixed $time
     * @return void
     */
    private function logSlowQuery($query, $time)
    {
        $request = request();
        $requestId = $request->attributes->get('request_id');

        $requestInfo = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'at' => now()->toDateTimeString()
        ];
        $uidKey = config('health.name.session.user_id');
        if ($uidKey) {
            $requestInfo['uid'] = Session::get($uidKey);
        }

        // \不替换SQL中的?，只记录原始SQL和绑定参数
        $sql = $this->truncateStr($query->sql, 500);
        $bindings = $this->truncateBindings($query->bindings, 100, 100); // 限制参数，每个100字符

        $backTrace = $this->getBackTrace();


        Log::channel($this->getLogChannelForSlowQuery())->warning('Slow Query', [
            'request_id' => $requestId,
            'time' => $time . 'ms',
            'sql' => $sql,
            'bindings' => $bindings,
            'request' => $requestInfo,
            'trace' => $backTrace
        ]);
    }

    // 限制SQL长度
    private function truncateStr($str, $maxLength)
    {
        return mb_strlen($str) > $maxLength
            ? mb_substr($str, 0, $maxLength) . '...'
            : $str;
    }

    // 限制绑定参数：最多5个，每个值截断到100字符
    private function truncateBindings($bindings, $maxCount, $maxValueLength)
    {
        $truncated = [];
        foreach ($bindings as $i => $value) {
            if ($i >= $maxCount)
                break;

            $valueStr = $this->truncateBindingValue($value, $maxValueLength);
            $truncated[] = $valueStr;
        }
        return $truncated;
    }

    private function truncateBindingValue($value, $maxLength)
    {
        if (is_string($value)) {
            return $this->truncateStr($value, $maxLength);
        }
        return $value;
    }

    /**
     * 获取精简的业务调用堆栈（排除框架、中间件、Provider 等）
     *
     * @param int $limit 最大返回层数
     * @return string
     */
    private function getBackTrace(int $limit = 100): string
    {
        $backtrace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit));
        $backtrace = $backtrace->reject(function ($trace) {
            $file = $trace['file'] ?? null;

            // 过滤掉框架内部调用和vendor目录
            return isset($file) &&
                (str_contains($file, 'vendor') ||
                    str_contains($file, 'Illuminate') ||
                    str_contains($file, 'Providers') ||
                    str_contains($file, 'Middleware')

                );
        })->values();

        $appPath = base_path() . DIRECTORY_SEPARATOR;

        $backtrace = $backtrace
            ->map(function ($trace, $key) use ($appPath) {
                $file = $trace['file'] ?? '';
                $relativeFile = str_replace($appPath, '', $file);

                if (!array_key_exists('line', $trace)) {
                    $sprintf = "#{$key} %s: %s%s%s()";
                    return sprintf(
                        $sprintf,
                        $relativeFile ?? '[internal function]',
                        $trace['class'] ?? '',
                        $trace['type'] ?? '',
                        $trace['function'] ?? ''
                    );
                }

                return sprintf(
                    "#{$key} %s(%d): %s%s%s()",
                    $relativeFile ?? '[internal function]',
                    $trace['line'] ?? 0,
                    $trace['class'] ?? '',
                    $trace['type'] ?? '',
                    $trace['function'] ?? ''
                );
            });
        $backtrace = $backtrace->implode(PHP_EOL);
        $backtrace = PHP_EOL . "[stacktrace]" . PHP_EOL . $backtrace . PHP_EOL;
        return $backtrace;
    }


    /**
     * 限流，每小时最多记录100条慢查询
     * @return bool
     */
    private function allowLogSlowQuery()
    {
        $hour = now()->format('YmdH');
        $key = 'slowlog:rate_limit:' . $hour;
        $maxPerHour = config('health.watchers.database.slow_query_rate_limit_per_hour', 100); // ;env('SLOW_QUERY_RATE_LIMIT_PER_HOUR', 100);

        Cache::add($key, 0, now()->addHour());


        $attempts = Cache::increment($key);

        if ($attempts <= $maxPerHour) {
            // 每20次再记录一下
            if ($attempts % 10 == 0) {
                Log::warning('慢查询日志触发次数：' . $attempts, ['hour' => $hour]);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 熔断，10秒内超过50次就熔断10分钟，防止数据库真的慢了就不记录了
     * @return bool
     */
    private function shouldBreakCircuit()
    {
        $breakerKey = 'slowlog:circuit_breaker';
        $triggerKey = 'slowlog:trigger_count';
        $threshold = config('health.watchers.database.slow_query_circuit_breaker_threshold', 50);// env('SLOW_QUERY_CIRCUIT_BREAKER_THRESHOLD', 50); // 10 秒内超过 50 次（即 >= 51）触发熔断

        // 检查是否已熔断
        if (Cache::has($breakerKey)) {
            return true;
        }

        // 确保计数器存在且有 TTL（仅首次设置）
        Cache::add($triggerKey, 0, now()->addSeconds(10));

        // 原子递增
        $count = Cache::increment($triggerKey);

        // \Log::warning('熔断触发次数', ['cnt' => $count]);
        // 判断是否达到阈值（注意：是 >= $threshold + 1）
        if ($count > $threshold) { // 也就是第 51 次

            if (Cache::add($breakerKey, true, now()->addMinutes(10))) {
                Log::warning('慢查询日志熔断触发，避免日志风暴', [
                    'current_count' => $count,
                    'threshold' => $threshold,
                    'breaker_minutes' => 10,
                ]);
            }
            return true;
        }

        return false;
    }
}