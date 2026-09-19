<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\SiteSettingsService;

/**
 * 维护模式。开启后除后台、登录/退出、验证码与超级管理员外，统一返回 503 维护页。
 * 开关位于后台「站点设置 → 维护模式」（settings.maintenance）。
 */
class MaintenanceMiddleware
{
    private const EXEMPT = ['/admin', '/login', '/logout', '/captcha'];

    public static function handle(Request $req): bool
    {
        if (!SiteSettingsService::bool('maintenance', false)) {
            return true;
        }
        $path = $req->routePath();
        foreach (self::EXEMPT as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }
        $user = AuthService::user();
        if ($user && (int) $user['role_level'] >= 100) {
            return true; // 超管始终可访问，便于关闭维护
        }
        if (is_ajax() || strpos($path, '/api/') === 0) {
            JsonResponse::fail(503, 'maintenance.on', []);
        }
        http_response_code(503);
        header('Retry-After: 3600');
        echo View::render('maintenance', [], null);
        exit;
    }
}
