<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\User;

/**
 * 账号与认证服务。权限校验不在此处；此处只负责身份与登录态。
 */
class AuthService
{
    private static ?array $current = null;

    public static function user(): ?array
    {
        $id = Session::userId();
        if ($id === null) {
            return null;
        }
        if (self::$current === null) {
            // 连接资料表，带上头像/签名（users 表本身没有这些字段）
            self::$current = Database::instance()->fetch(
                'SELECT u.*, p.avatar AS avatar, p.bio AS bio
                   FROM users u
                   LEFT JOIN user_profiles p ON p.user_id = u.id
                  WHERE u.id = ? AND u.status IN ("active","pending")
                  LIMIT 1',
                [$id]
            );
        }
        return self::$current;
    }

    public static function refresh(): void
    {
        self::$current = null;
    }

    public static function userId(): ?int
    {
        return Session::userId();
    }

    public static function authenticate(string $login, string $password): ?array
    {
        $user = Database::instance()->fetch(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND status IN ("active","pending","deleting") LIMIT 1',
            [$login, $login]
        );
        if (!$user) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        // 注销冷静期内重新登录 → 自动撤销注销（账号恢复）
        if ($user['status'] === 'deleting') {
            SettingsService::cancelDeletion((int) $user['id']);
            $user['status'] = 'active';
            $user['deleted_at'] = null;
        }
        return $user;
    }

    public static function login(int $userId, bool $remember = false): void
    {
        Session::login($userId, $remember);
        self::$current = null;
    }

    public static function logout(): void
    {
        Session::logout();
        self::$current = null;
    }

    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($password, $algo);
    }

    public static function register(array $data): int
    {
        $mode = SiteSettingsService::get('register_mode', null)
            ?: \App\Core\Config::get('app.register_mode', 'open');
        $status = $mode === 'email' ? 'pending' : 'active';
        $db = Database::instance();
        $userId = $db->insert('users', [
            'username'   => $data['username'],
            'email'      => $data['email'],
            'password_hash' => self::hashPassword($data['password']),
            'nickname'   => $data['nickname'] ?? $data['username'],
            'status'     => $status,
            'created_at' => now_utc(),
        ]);
        $db->insert('user_profiles', ['user_id' => $userId]);
        // 默认设置
        $db->insert('user_settings', ['user_id' => $userId, 'key' => 'theme', 'value' => \App\Core\Config::get('app.theme_default', 'graphite')]);
        $db->insert('user_settings', ['user_id' => $userId, 'key' => 'appearance', 'value' => \App\Core\Config::get('app.mode_default', 'dark')]);
        return $userId;
    }

    public static function isUsernameTaken(string $username): bool
    {
        return Database::instance()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]) !== null;
    }

    public static function isEmailTaken(string $email): bool
    {
        return Database::instance()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]) !== null;
    }
}
