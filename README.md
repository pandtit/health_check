
# Laravel Health Check

Laravel Health Check: Monitors database/cache/queue connectivity with IP whitelist security, providing real-time status checks via API endpoints.

Laravel 健康监控包：通过 API 端点实时监测数据库/缓存/队列的连通性状态，支持 IP 白名单访问控制，确保关键服务可用性。

## Installation

```bash

# 安装包
composer require Pandtit/health_check

```

## publish config

```bash
php artisan vendor:publish --provider="Pandtit\HealthCheck\HealthCheckServiceProvider" --tag="config"

```


## Environment Configuration

You can configure via `.env`:

```env
# 启用健康检查
HEALTH_CHECK_ENABLED=true

# 允许访问检查接口的IP,逗号分隔
HEALTH_CHECK_IPS=127.0.0.1,192.168.1.0/24,10.0.0.1

# 是否检查更多
HEALTH_CHECK_DB_ENABLED=false
HEALTH_CHECK_CACHE_ENABLED=false
HEALTH_CHECK_QUEUE_ENABLED=false
```


## usage

# access: get http://your-app/api/health


## healthy

## simple
```json
{
    "errcode": 0,
    "errmsg": null,
    "status": "healthy",
    "data": [],
    "at": "2025-07-30T02:12:32.063030Z",
    "service": "southcn_feed"
}
```

### multiple
```json
{
    "errcode": 0,
    "errmsg": null,
    "status": "healthy",
    "data": {
        "database": "healthy",
        "cache": "healthy",
        "queue": "healthy"
    },
    "at": "2025-07-29T04:03:21.436347Z",
    "service": "your_app"
}
```
## unhealty
```json
{
    "errcode": 0,
    "errmsg": "Queue check failed",
    "status": "unhealthy",
    "data": {
        "database": "unhealthy",
        "cache": "unhealthy",
        "queue": "unhealthy"
    },
    "at": "2025-07-29T06:41:07.036335Z",
    "service": "Laravel"
}
```

# not in ip

```text
status 403
```

