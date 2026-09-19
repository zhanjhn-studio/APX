<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Core\WafService;

/**
 * WAF 中间件。必须在路由匹配之前执行——攻击请求不进入业务层。
 * 默认观察模式只记录；防御模式按评分处置（LOG / CHALLENGE / BLOCK / BAN）。
 */
class WafMiddleware
{
    public static function handle(Request $req): bool
    {
        if (defined('APX_INSTALLING')) {
            return true;
        }
        if (!is_file(APP_ROOT . '/config/database.php')) {
            return true;
        }
        try {
            if (WafService::isBanned($req->ip())) {
                abort(403, 'waf.banned');
                return false;
            }
            $payload = $req->uri() . ' ' . http_build_query($req->get()) . ' ' . http_build_query($req->post());
            $result = WafService::evaluate($req);
            if ($result['score'] > 0) {
                WafService::log($req->ip(), Session::userId(), $result, $payload);
            }
            if (Config::get('security.waf.mode') === 'defense') {
                if ($result['action'] === 'block') {
                    abort(403, 'waf.blocked');
                    return false;
                }
                if ($result['action'] === 'ban') {
                    abort(403, 'waf.banned');
                    return false;
                }
                if ($result['action'] === 'challenge') {
                    abort(429, 'waf.challenge');
                    return false;
                }
            }
        } catch (\Throwable $e) {
            // 安全层异常绝不能阻断正常请求
            return true;
        }
        return true;
    }
}
