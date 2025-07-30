<?php

use Illuminate\Support\Facades\Route;
use Pandtit\HealthCheck\HealthCheckController;
use Pandtit\HealthCheck\Middleware\CheckHealthIpWhitelist;

Route::group(['prefix' => 'api'], function () {
    if (config('health.enabled', true)) {
        Route::get('health', HealthCheckController::class)
            ->middleware(CheckHealthIpWhitelist::class)
            ->name('health.check');
    }
});