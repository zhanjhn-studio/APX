<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\DeviceService;

/**
 * 登录拦截。页面请求未登录跳转登录页，AJAX 返回 401。
 * 同时校验「会话纪元」：被远程下线的登录态立即失效。
 */
class AuthMiddleware
{
    public static function handle(Request $req): bool
    {
        $user = AuthService::user();
        if ($user) {
            $uid = (int) $user['id'];
            if (!DeviceService::assertEpoch($uid)) {
                AuthService::logout();
                if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
                    JsonResponse::fail(401, 'auth.required', []);
                }
                redirect('/login');
                return false;
            }
            DeviceService::touch($uid);
            return true;
        }
        if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
            JsonResponse::fail(401, 'auth.required', []);
        }
        redirect('/login');
        return false;
    }
}
