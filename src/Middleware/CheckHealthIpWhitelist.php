<?php

namespace Pandtit\HealthCheck\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class CheckHealthIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();
        $allowedIps = config('health.allowed_ips', []);

        if (empty($allowedIps)) {
            return response()->json(['errcode' => 403, 'errmsg' => 'Forbidden'], 403);
        }

        foreach ($allowedIps as $allowed) {
            $allowed = trim($allowed);
            if (empty($allowed)) {
                continue;
            }

            // 使用 Symfony 的 IpUtils 支持 CIDR
            if (IpUtils::checkIp($clientIp, $allowed)) {
                return $next($request);
            }
        }

        return response()->json(['errcode' => 403, 'errmsg' => 'Forbidden'], 403);
    }
}