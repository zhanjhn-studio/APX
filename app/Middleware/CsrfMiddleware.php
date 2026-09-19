<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\JsonResponse;
use App\Core\Request;

/**
 * CSRF 校验。GET/HEAD 跳过，其余方法校验头或表单中的令牌。
 */
class CsrfMiddleware
{
    public static function handle(Request $req): bool
    {
        if ($req->isGet() || $req->method() === 'HEAD' || $req->method() === 'OPTIONS') {
            return true;
        }
        $name = Config::get('security.csrf.token_name', 'csrf_token');
        $token = $req->header(Config::get('security.csrf.header_name', 'X-CSRF-Token')) ?: $req->post($name);
        if (Csrf::validate($token)) {
            return true;
        }
        if (is_ajax() || strpos($req->routePath(), '/api/') === 0) {
            JsonResponse::fail(419, 'csrf.invalid', []);
        }
        abort(419, 'csrf.invalid');
        return false;
    }
}
