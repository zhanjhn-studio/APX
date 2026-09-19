<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Request;
use App\Services\AuthService;

/**
 * 游客拦截。已登录用户访问登录/注册页时重定向到首页。
 */
class GuestMiddleware
{
    public static function handle(Request $req): bool
    {
        if (!AuthService::user()) {
            return true;
        }
        if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
            return true;
        }
        redirect('/home');
        return false;
    }
}
