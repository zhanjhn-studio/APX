<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Request;

/**
 * 安全响应头。WAF 之外的纵深防护层。
 */
class SecureHeadersMiddleware
{
    public static function handle(Request $req): bool
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'");
        header('X-DNS-Prefetch-Control: off');
        return true;
    }
}
