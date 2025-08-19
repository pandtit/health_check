<?php

namespace Pandtit\HealthCheck;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Pandtit\HealthCheck\Middleware\CheckHealthIpWhitelist;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/health.php',
            'health'
        );
    }

    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/health.php' => config_path('health.php'),
        ], 'config');


        // 注册路由（使用自定义方法）
        $this->registerHealthRoutes();

    }

    /**
     * 注册健康检查路由（支持动态中间件）
     */
    protected function registerHealthRoutes()
    {
        if (!config('health.enabled', true)) {
            return;
        }

        // 防止重复注册（可选）
        if (app()->has('health.routes.registered')) {
            return;
        }
        app()->instance('health.routes.registered', true);


        Route::group(['prefix' => 'api'], function () {
            // 构建中间件数组
            $middleware = [CheckHealthIpWhitelist::class];

            // 如果启用了限流，添加 throttle 中间件
            if (!empty(config('health.rate_limiting.enabled'))) {
                $maxAttempts = config('health.rate_limiting.max_attempts') ?? 60;
                $decayMinutes = config('health.rate_limiting.decay_minutes') ?? 1;
                $middleware[] = "throttle:{$maxAttempts},{$decayMinutes}";
            }

            Route::get('health', HealthCheckController::class)
                ->middleware($middleware)
                ->name('health.check');
        });
    }
}