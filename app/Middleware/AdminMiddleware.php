<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\PermissionService;

/**
 * 后台访问拦截。超管（role_level >= 100）或持有 admin.access 权限的角色可进入；
 * 细粒度操作仍由 PermissionMiddleware 按权限点二次校验。
 */
class AdminMiddleware
{
    public static function handle(Request $req): bool
    {
        $user = AuthService::user();
        $allowed = $user !== null
            && ((int) $user['role_level'] >= 100 || PermissionService::can('admin.access'));
        if ($allowed) {
            return true;
        }
        if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
            JsonResponse::fail(403, 'permission.denied', []);
        }
        abort(403, 'permission.denied');
        return false;
    }
}
