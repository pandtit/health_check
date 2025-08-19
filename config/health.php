<?php

return [
    // 是否启用健康检测接口
    'enabled' => env('HEALTH_CHECK_ENABLED', true),


    // 允许访问的 IP 白名单（支持 CIDR 格式）
    'allowed_ips' => array_filter(explode(',', env('HEALTH_CHECK_IPS', ''))) ?: [
        '127.0.0.1',
        '::1',
    ],

    // 允许代理, 默认获取真实
    'allow_proxy' => env('HEALTH_CHECK_IPS_ALLOW_PROXY', false),

    'checks' => [
        'database' => env('HEALTH_CHECK_DB_ENABLED', false),
        'cache' => env('HEALTH_CHECK_CACHE_ENABLED', false),
        'queue' => env('HEALTH_CHECK_QUEUE_ENABLED', false),
    ],

    // 控制是否记录日志
    'log_enabled' => env('HEALTH_CHECK_LOG_ENABLED', true),



    // 频率限制,防止健康检测接口被频繁调用
    'rate_limiting' => [
        'enabled' => env('HEALTH_CHECK_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('HEALTH_CHECK_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('HEALTH_CHECK_DECAY_MINUTES', 1),
    ],

    // 频率限制对应的中间件
    'middleware' => ['api'],

];