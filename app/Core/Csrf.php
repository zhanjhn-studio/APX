<?php
declare(strict_types=1);
namespace App\Core;

/**
 * CSRF 令牌。表单隐藏域 + X-CSRF-Token 头双通道。
 * 令牌绑定会话，超时自动轮换。
 */
class Csrf
{
    public static function token(): string
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            Session::start();
        }
        if (empty($_SESSION['_csrf']) || (int) ($_SESSION['_csrf_exp'] ?? 0) < time()) {
            self::renew();
        }
        return $_SESSION['_csrf'];
    }

    private static function renew(): string
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_exp'] = time() + (int) Config::get('security.csrf.ttl', 7200);
        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        if (PHP_SESSION_ACTIVE !== session_status()) {
            Session::start();
        }
        if (empty($_SESSION['_csrf']) || (int) ($_SESSION['_csrf_exp'] ?? 0) < time()) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }
}
