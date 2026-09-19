<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Cache;
use App\Core\Config;
use App\Core\JsonResponse;
use App\Core\Request;

/**
 * 基础限流。按 IP + 路径计数，超过阈值拒绝。登录等敏感接口可单独配置。
 */
class RateLimitMiddleware
{
    public static function handle(Request $req): bool
    {
        $limit = Config::get('security.rate_limit.default', ['requests' => 120, 'window' => 60]);
        $key = 'rl:' . $req->ip() . ':' . md5($req->path());
        $count = (int) Cache::get($key, 0);
        if ($count >= (int) $limit['requests']) {
            if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
                JsonResponse::fail(429, 'rate.limited', []);
            }
            abort(429, 'rate.limited');
            return false;
        }
        Cache::set($key, $count + 1, (int) $limit['window']);
        return true;
    }
}
