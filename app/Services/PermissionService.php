<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 权限校验服务。role_level >= 100 视为超级管理员，绕过具体权限点。
 * 普通管理员按 user_roles → role_permissions → permissions 聚合。结果按用户缓存。
 */
class PermissionService
{
    private static array $cache = [];

    public static function can(string $permission): bool
    {
        $user = AuthService::user();
        if (!$user) {
            return false;
        }
        if ((int) $user['role_level'] >= 100) {
            return true;
        }
        $uid = (int) $user['id'];
        if (!isset(self::$cache[$uid])) {
            self::$cache[$uid] = self::load($uid);
        }
        return self::$cache[$uid][$permission] ?? false;
    }

    public static function refresh(): void
    {
        self::$cache = [];
    }

    private static function load(int $uid): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT p.code FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?',
            [$uid]
        );
        $perms = [];
        foreach ($rows as $r) {
            $perms[$r['code']] = true;
        }
        return $perms;
    }
}
