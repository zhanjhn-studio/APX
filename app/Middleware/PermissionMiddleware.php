<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\PermissionService;

/**
 * 权限校验。路由中写为 ['permission', 'admin.user']。
 */
class PermissionMiddleware
{
    public static function handle(Request $req, string $permission = ''): bool
    {
        if (PermissionService::can($permission)) {
            return true;
        }
        if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
            JsonResponse::fail(403, 'permission.denied', []);
        }
        abort(403, 'permission.denied');
        return false;
    }
}
