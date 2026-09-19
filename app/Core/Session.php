<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 会话管理。Cookie httponly + samesite，指纹绑定（UA + IP 段），
 * 定期重生成会话 ID，新设备/异常自动要求重新登录。
 */
class Session
{
    public static function start(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            return;
        }
        $cfg = Config::get('security.session');
        session_name($cfg['name'] ?? 'apx_session');
        session_set_cookie_params([
            'lifetime' => (int) ($cfg['lifetime'] ?? 1209600),
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) ($cfg['cookie_secure'] ?? false),
            'httponly' => true,
            'samesite' => $cfg['cookie_samesite'] ?? 'Lax',
        ]);
        session_start();
        self::verifyFingerprint();
    }

    private static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . self::ipPrefix());
    }

    private static function ipPrefix(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ':') !== false) {
            return $ip;
        }
        $p = explode('.', $ip);
        return ($p[0] ?? '0') . '.' . ($p[1] ?? '0') . '.' . ($p[2] ?? '0');
    }

    private static function verifyFingerprint(): void
    {
        if (!isset($_SESSION['_fp'])) {
            $_SESSION['_fp'] = self::fingerprint();
            $_SESSION['_regenerated'] = time();
            return;
        }
        if (!hash_equals($_SESSION['_fp'], self::fingerprint())) {
            self::destroy();
            session_start();
            $_SESSION['_fp'] = self::fingerprint();
        }
        $cfg = Config::get('security.session');
        $prob = (int) ($cfg['regenerate_probability'] ?? 10);
        if (empty($_SESSION['_regenerated']) || time() - (int) $_SESSION['_regenerated'] > 300) {
            if (random_int(1, 100) <= max(1, $prob)) {
                session_regenerate_id(true);
            }
            $_SESSION['_regenerated'] = time();
        }
    }

    public static function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget($key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has($key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function flash($key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash($key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function setOld(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    public static function old($key)
    {
        $value = $_SESSION['_old'][$key] ?? null;
        unset($_SESSION['_old'][$key]);
        return $value;
    }

    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function login(int $userId, bool $remember = false): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['_login_at'] = time();
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['remember_token'] = $token;
        }
    }

    public static function logout(): void
    {
        self::destroy();
    }

    public static function destroy(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_unset();
            session_destroy();
        }
    }
}
