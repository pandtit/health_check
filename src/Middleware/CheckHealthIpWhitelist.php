<?php

namespace Pandtit\HealthCheck\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class CheckHealthIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('health.allowed_ips', []);
        $allowProxy = config('health.allow_proxy', false);
        $logEnabled = config('health.log_enabled', false); // 是否开启日志

        if (empty($allowedIps)) {
            $message = 'Health check denied: no allowed IPs configured.';
            if ($logEnabled) {
                Log::warning($message);
            }
            return response()->json(['errcode' => 403, 'errmsg' => 'Forbidden'], 403);
        }

        // 使用自定义方法获取客户端 IP
        $clientIp = $this->getClientIp($request, $allowProxy);

        if (!$clientIp) {
            $message = 'Health check denied: unable to determine client IP.';
            if ($logEnabled) {
                Log::warning($message . ' Request from: ' . $request->ip());
            }
            return response()->json(['errcode' => 403, 'errmsg' => 'Forbidden'], 403);
        }


        $isAllowed = false;
        foreach ($allowedIps as $allowed) {
            $allowed = trim($allowed);
            if (empty($allowed)) {
                continue;
            }

            if (IpUtils::checkIp($clientIp, $allowed)) {
                $isAllowed = true;
                break;
            }
        }

        if ($isAllowed) {
            return $next($request);
        } else {
            $message = "Health check denied: IP {$clientIp} is not allowed.";
            if ($logEnabled) {
                Log::warning($message, [
                    'client_ip' => $clientIp,
                    'client_ips' => $request->ips(),
                    'allow_ips' => $allowedIps,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_agent' => $request->userAgent()
                ]);
            }
            return response()->json(['errcode' => 403, 'errmsg' => 'Forbidden'], 403);
        }

    }

    /**
     * 获取客户端真实 IP
     *
     * @param Request $request
     * @param bool $allowProxy
     * @return string|null
     */
    private function getClientIp(Request $request, bool $allowProxy): ?string
    {
        if (!$allowProxy) {
            return $request->ip();
        }

        $headersToCheck = [
            'CF-Connecting-IP',
            'Fastly-Client-IP',
            'True-Client-IP',
            'X-Real-IP',
            'X-Client-IP',
            'X-Forwarded-For',
        ];

        foreach ($headersToCheck as $header) {
            $value = $request->header($header);

            if (empty($value)) {
                continue;
            }

            $ips = array_map('trim', explode(',', $value));
            foreach ($ips as $ip) {
                if ($this->isPublicIp($ip)) {
                    return $ip;
                }
            }
        }

        // Fallback 到直接连接 IP
        return $request->ip();
    }

    /**
     * 判断是否为公网 IP
     */
    private function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            'fc00::/7',
            'fe80::/10',
            '::1/128', // ✅ 修正为 CIDR 格式
        ];

        foreach ($privateRanges as $range) {
            if (IpUtils::checkIp($ip, $range)) {
                return false;
            }
        }

        return true;
    }
}