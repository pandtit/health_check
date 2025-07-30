<?php

namespace Pandtit\HealthCheck;

use Illuminate\Support\ServiceProvider;

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


        if ($this->app['config']->get('health.enabled', true)) {

            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

    }
}