<?php

return [
    // 是否启用健康检测接口
    'enabled' => env('HEALTH_CHECK_ENABLED', true),

    // 允许访问的 IP 白名单（支持 CIDR 格式）
    'allowed_ips' => array_filter(explode(',', env('HEALTH_CHECK_IPS', ''))) ?: [
        '127.0.0.1',
        '::1',
    ],

    'checks' => [
        'database' => env('HEALTH_CHECK_DB_ENABLED', false),
        'cache' => env('HEALTH_CHECK_CACHE_ENABLED', false),
        'queue' => env('HEALTH_CHECK_QUEUE_ENABLED', false),
    ],
];