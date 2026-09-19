<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 管理后台业务：用户 / 内容 / 群组 / 角色权限 / 站点设置 / 主题语言 / 统计 / 日志。
 * 全部治理动作在此校验并写入 admin_logs，控制器只取参与组织响应。
 */
class AdminService
{
    public const PER_PAGE = 20;

    private static function db(): Database
    {
        return Database::instance();
    }

    /** 写管理员操作日志（禁止记录敏感数据）。 */
    public static function log(string $action, string $targetType = '', ?int $targetId = null, string $detail = ''): void
    {
        $uid = AuthService::userId();
        if ($uid === null) {
            return;
        }
        self::db()->insert('admin_logs', [
            'admin_id'    => $uid,
            'action'      => mb_substr($action, 0, 64),
            'target_type' => mb_substr($targetType, 0, 32),
            'target_id'   => $targetId,
            'detail'      => mb_substr($detail, 0, 500),
            'ip'          => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'created_at'  => now_utc(),
        ]);
    }

    // ==================================================================
    // 概览与统计
    // ==================================================================

    public static function overview(): array
    {
        $db = self::db();
        return [
            'users'         => (int) $db->column('SELECT COUNT(*) FROM users'),
            'users_active'  => (int) $db->column("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'users_pending' => (int) $db->column("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
            'users_banned'  => (int) $db->column("SELECT COUNT(*) FROM users WHERE status = 'banned'"),
            'users_deleting'=> (int) $db->column("SELECT COUNT(*) FROM users WHERE status = 'deleting'"),
            'posts'         => (int) $db->column('SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL'),
            'posts_removed' => (int) $db->column('SELECT COUNT(*) FROM posts WHERE deleted_at IS NOT NULL'),
            'comments'      => (int) $db->column('SELECT COUNT(*) FROM post_comments WHERE deleted_at IS NULL'),
            'groups'        => (int) $db->column("SELECT COUNT(*) FROM groups WHERE status = 'active'"),
            'messages'      => (int) $db->column('SELECT COUNT(*) FROM messages'),
            'reports'       => (int) $db->column("SELECT COUNT(*) FROM reports WHERE status = 'pending'"),
            'ip_bans'       => (int) $db->column('SELECT COUNT(*) FROM ip_bans'),
            'waf_hits'      => (int) $db->column('SELECT COUNT(*) FROM waf_logs'),
            'online'        => (int) $db->column('SELECT COUNT(*) FROM users WHERE last_active_at > ?', [date('Y-m-d H:i:s', time() - 300)]),
        ];
    }

    /** 近 N 日趋势（注册 / 动态 / 登录），MySQL 5.7 兼容的按日分组。 */
    public static function trend(int $days = 14): array
    {
        $db = self::db();
        $from = date('Y-m-d 00:00:00', time() - ($days - 1) * 86400);
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[date('Y-m-d', time() - $i * 86400)] = ['users' => 0, 'posts' => 0, 'logins' => 0, 'messages' => 0];
        }
        $merge = function (array $rows, string $field) use (&$out): void {
            foreach ($rows as $r) {
                $d = (string) $r['d'];
                if (isset($out[$d])) {
                    $out[$d][$field] = (int) $r['c'];
                }
            }
        };
        $merge($db->fetchAll('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE created_at >= ? GROUP BY DATE(created_at)', [$from]), 'users');
        $merge($db->fetchAll('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM posts WHERE created_at >= ? GROUP BY DATE(created_at)', [$from]), 'posts');
        $merge($db->fetchAll("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM login_logs WHERE created_at >= ? AND result = 'success' GROUP BY DATE(created_at)", [$from]), 'logins');
        $merge($db->fetchAll('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM messages WHERE created_at >= ? GROUP BY DATE(created_at)', [$from]), 'messages');

        $rows = [];
        foreach ($out as $date => $v) {
            $rows[] = ['date' => $date] + $v;
        }
        return $rows;
    }

    /** 榜单：活跃用户 / 高产作者 / 热门话题 / 大群。 */
    public static function leaderboards(): array
    {
        $db = self::db();
        return [
            'active_users' => $db->fetchAll(
                "SELECT u.id, u.username, u.nickname, u.last_active_at, u.experience, up.avatar
                 FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id
                 WHERE u.status = 'active' ORDER BY u.last_active_at DESC LIMIT 8"
            ),
            'top_authors' => $db->fetchAll(
                "SELECT u.id, u.username, u.nickname, COUNT(p.id) AS post_count, COALESCE(SUM(p.like_count),0) AS likes
                 FROM users u JOIN posts p ON p.user_id = u.id AND p.deleted_at IS NULL
                 GROUP BY u.id, u.username, u.nickname
                 ORDER BY post_count DESC, likes DESC LIMIT 8"
            ),
            'hot_topics' => $db->fetchAll('SELECT id, name, slug, post_count, follower_count FROM topics ORDER BY post_count DESC LIMIT 8'),
            'big_groups' => $db->fetchAll("SELECT id, name, slug, member_count, post_count FROM groups WHERE status = 'active' ORDER BY member_count DESC LIMIT 8"),
        ];
    }

    // ==================================================================
    // 用户管理
    // ==================================================================

    public static function users(string $keyword = '', string $status = '', int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if ($keyword !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $where[] = '(u.username LIKE ? OR u.nickname LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($status !== '' && in_array($status, ['active', 'pending', 'banned', 'deleting'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        $w = implode(' AND ', $where);
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) $db->column("SELECT COUNT(*) FROM users u WHERE $w", $params);
        $rows = $db->fetchAll(
            "SELECT u.id, u.username, u.nickname, u.email, u.status, u.role_level, u.level, u.experience,
                    u.two_factor_enabled, u.last_active_at, u.created_at,
                    (SELECT GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ')
                       FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS role_names,
                    (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.deleted_at IS NULL) AS post_count
             FROM users u
             WHERE $w
             ORDER BY u.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function setUserStatus(int $userId, string $status): bool
    {
        if (!in_array($status, ['active', 'pending', 'banned'], true)) {
            return false;
        }
        $me = AuthService::userId();
        if ($userId === $me) {
            return false; // 不允许对待自己
        }
        $user = self::db()->fetch('SELECT id, username, status FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user) {
            return false;
        }
        self::db()->update('users', ['status' => $status], 'id = ?', [$userId]);
        self::log('user.status', 'user', $userId, $status);
        return true;
    }

    /** 授予 / 撤销超级管理员（role_level = 100）。 */
    public static function setSuperAdmin(int $userId, bool $grant): bool
    {
        if ($userId === AuthService::userId()) {
            return false;
        }
        $user = self::db()->fetch('SELECT id, role_level FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user) {
            return false;
        }
        if (!$grant && (int) $user['role_level'] >= 100) {
            $remaining = (int) self::db()->column('SELECT COUNT(*) FROM users WHERE role_level >= 100 AND id != ?', [$userId]);
            if ($remaining === 0) {
                return false; // 必须保留至少一个超级管理员
            }
        }
        self::db()->update('users', ['role_level' => $grant ? 100 : 0], 'id = ?', [$userId]);
        self::log($grant ? 'user.grant_super' : 'user.revoke_super', 'user', $userId);
        PermissionService::refresh();
        return true;
    }

    public static function roles(): array
    {
        return self::db()->fetchAll(
            'SELECT r.id, r.name, r.slug, r.description, r.is_system,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count
             FROM roles r ORDER BY r.id'
        );
    }

    public static function assignRole(int $userId, int $roleId): bool
    {
        if ($userId <= 0 || $roleId <= 0) {
            return false;
        }
        self::db()->statement('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
        self::log('user.role_grant', 'user', $userId, 'role#' . $roleId);
        PermissionService::refresh();
        return true;
    }

    public static function revokeRole(int $userId, int $roleId): bool
    {
        $n = self::db()->delete('user_roles', 'user_id = ? AND role_id = ?', [$userId, $roleId]);
        if ($n > 0) {
            self::log('user.role_revoke', 'user', $userId, 'role#' . $roleId);
            PermissionService::refresh();
        }
        return $n > 0;
    }

    public static function userRoles(int $userId): array
    {
        return self::db()->fetchAll(
            'SELECT r.id, r.name, r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.id',
            [$userId]
        );
    }

    // ==================================================================
    // 内容管理
    // ==================================================================

    public static function posts(string $keyword = '', string $state = '', int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if ($keyword !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $where[] = 'p.body LIKE ?';
            $params[] = $like;
        }
        if ($state === 'removed') {
            $where[] = 'p.deleted_at IS NOT NULL';
        } elseif ($state === 'visible') {
            $where[] = 'p.deleted_at IS NULL';
        }
        $w = implode(' AND ', $where);
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM posts p WHERE $w", $params);
        $items = $db->fetchAll(
            "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count, p.deleted_at, p.created_at,
                    u.username, u.nickname,
                    (SELECT COUNT(*) FROM reports r WHERE r.target_type = 'post' AND r.target_id = p.id) AS report_count
             FROM posts p JOIN users u ON u.id = p.user_id
             WHERE $w ORDER BY p.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function setPostRemoved(int $postId, bool $removed): bool
    {
        $post = self::db()->fetch('SELECT id FROM posts WHERE id = ? LIMIT 1', [$postId]);
        if (!$post) {
            return false;
        }
        self::db()->update('posts', ['deleted_at' => $removed ? now_utc() : null], 'id = ?', [$postId]);
        self::log($removed ? 'post.remove' : 'post.restore', 'post', $postId);
        return true;
    }

    public static function comments(string $keyword = '', int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if ($keyword !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $where[] = 'c.body LIKE ?';
            $params[] = $like;
        }
        $w = implode(' AND ', $where);
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM post_comments c WHERE $w", $params);
        $items = $db->fetchAll(
            "SELECT c.id, c.post_id, c.user_id, c.body, c.deleted_at, c.created_at, u.username, u.nickname
             FROM post_comments c JOIN users u ON u.id = c.user_id
             WHERE $w ORDER BY c.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function setCommentRemoved(int $commentId, bool $removed): bool
    {
        $row = self::db()->fetch('SELECT id FROM post_comments WHERE id = ? LIMIT 1', [$commentId]);
        if (!$row) {
            return false;
        }
        self::db()->update('post_comments', ['deleted_at' => $removed ? now_utc() : null], 'id = ?', [$commentId]);
        self::log($removed ? 'comment.remove' : 'comment.restore', 'comment', $commentId);
        return true;
    }

    // ==================================================================
    // 群组管理
    // ==================================================================

    public static function groups(string $keyword = '', string $state = 'active', int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if ($keyword !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $where[] = '(g.name LIKE ? OR g.slug LIKE ?)';
            array_push($params, $like, $like);
        }
        if (in_array($state, ['active', 'disbanded'], true)) {
            $where[] = 'g.status = ?';
            $params[] = $state;
        }
        $w = implode(' AND ', $where);
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM groups g WHERE $w", $params);
        $items = $db->fetchAll(
            "SELECT g.id, g.name, g.slug, g.visibility, g.member_count, g.post_count, g.status, g.created_at,
                    u.username AS owner_username, u.nickname AS owner_nickname
             FROM groups g JOIN users u ON u.id = g.owner_id
             WHERE $w ORDER BY g.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function disbandGroup(int $groupId): bool
    {
        $group = self::db()->fetch('SELECT id, status FROM groups WHERE id = ? LIMIT 1', [$groupId]);
        if (!$group || $group['status'] === 'disbanded') {
            return false;
        }
        self::db()->update('groups', ['status' => 'disbanded'], 'id = ?', [$groupId]);
        self::log('group.disband', 'group', $groupId);
        return true;
    }

    public static function restoreGroup(int $groupId): bool
    {
        $group = self::db()->fetch('SELECT id FROM groups WHERE id = ? LIMIT 1', [$groupId]);
        if (!$group) {
            return false;
        }
        self::db()->update('groups', ['status' => 'active'], 'id = ?', [$groupId]);
        self::log('group.restore', 'group', $groupId);
        return true;
    }

    // ==================================================================
    // 黑名单（用户之间的拉黑关系）
    // ==================================================================

    public static function blockedUsers(string $keyword = '', int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if ($keyword !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $where[] = '(a.username LIKE ? OR a.nickname LIKE ? OR b.username LIKE ? OR b.nickname LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column(
            "SELECT COUNT(*) FROM blocks bl
             JOIN users a ON a.id = bl.user_id
             JOIN users b ON b.id = bl.target_id
             WHERE $w",
            $params
        );
        $items = $db->fetchAll(
            "SELECT bl.id, bl.created_at,
                    a.id AS user_id, a.username, a.nickname, ap.avatar,
                    b.id AS target_id, b.username AS target_username, b.nickname AS target_nickname, bp.avatar AS target_avatar
             FROM blocks bl
             JOIN users a ON a.id = bl.user_id
             JOIN users b ON b.id = bl.target_id
             LEFT JOIN user_profiles ap ON ap.user_id = a.id
             LEFT JOIN user_profiles bp ON bp.user_id = b.id
             WHERE $w
             ORDER BY bl.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function removeBlock(int $id): bool
    {
        $row = self::db()->fetch('SELECT id, user_id, target_id FROM blocks WHERE id = ? LIMIT 1', [$id]);
        if (!$row) {
            return false;
        }
        self::db()->delete('blocks', 'id = ?', [$id]);
        self::log('block.remove', 'block', $id, (int) $row['user_id'] . '->' . (int) $row['target_id']);
        return true;
    }

    // ==================================================================
    // 角色与权限
    // ==================================================================

    public static function permissions(): array
    {
        return self::db()->fetchAll('SELECT id, code, name, `group` FROM permissions ORDER BY `group`, id');
    }

    public static function rolePermissionIds(int $roleId): array
    {
        $rows = self::db()->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]);
        return array_map(fn($r) => (int) $r['permission_id'], $rows);
    }

    /** 覆盖式保存角色权限。super_admin 角色不可编辑（恒为全部权限）。 */
    public static function saveRolePermissions(int $roleId, array $permissionIds): bool
    {
        $role = self::db()->fetch('SELECT id, slug FROM roles WHERE id = ? LIMIT 1', [$roleId]);
        if (!$role || $role['slug'] === 'super_admin') {
            return false;
        }
        $valid = [];
        foreach (self::permissions() as $p) {
            $valid[(int) $p['id']] = true;
        }
        $db = self::db();
        $db->beginTransaction();
        try {
            $db->delete('role_permissions', 'role_id = ?', [$roleId]);
            foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
                if ($pid > 0 && isset($valid[$pid])) {
                    $db->insertIgnore('role_permissions', ['role_id' => $roleId, 'permission_id' => $pid]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        self::log('role.permissions', 'role', $roleId, (string) count($permissionIds));
        PermissionService::refresh();
        return true;
    }

    public static function createRole(string $name, string $slug, string $description): int
    {
        $slug = preg_replace('/[^a-z0-9_]/', '', mb_strtolower(trim($slug), 'UTF-8'));
        if ($name === '' || $slug === '') {
            return 0;
        }
        if (self::db()->fetch('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])) {
            return 0;
        }
        $id = self::db()->insert('roles', [
            'name'        => mb_substr($name, 0, 32),
            'slug'        => mb_substr($slug, 0, 32),
            'description' => mb_substr($description, 0, 128),
            'is_system'   => 0,
            'created_at'  => now_utc(),
        ]);
        self::log('role.create', 'role', $id, $slug);
        return $id;
    }

    public static function deleteRole(int $roleId): bool
    {
        $role = self::db()->fetch('SELECT id, slug, is_system FROM roles WHERE id = ? LIMIT 1', [$roleId]);
        if (!$role || (int) $role['is_system'] === 1) {
            return false; // 内置角色不可删除
        }
        self::db()->delete('roles', 'id = ?', [$roleId]);
        self::log('role.delete', 'role', $roleId, (string) $role['slug']);
        PermissionService::refresh();
        return true;
    }

    // ==================================================================
    // 主题与语言
    // ==================================================================

    public static function themes(): array
    {
        return self::db()->fetchAll('SELECT id, slug, name, is_default, is_enabled FROM themes ORDER BY id');
    }

    public static function setThemeEnabled(int $id, bool $enabled): bool
    {
        if (!self::db()->fetch('SELECT id FROM themes WHERE id = ? LIMIT 1', [$id])) {
            return false;
        }
        self::db()->update('themes', ['is_enabled' => $enabled ? 1 : 0], 'id = ?', [$id]);
        self::log('theme.toggle', 'theme', $id, $enabled ? 'on' : 'off');
        return true;
    }

    public static function setDefaultTheme(string $slug): bool
    {
        $theme = self::db()->fetch('SELECT id FROM themes WHERE slug = ? LIMIT 1', [$slug]);
        if (!$theme) {
            return false;
        }
        $db = self::db();
        $db->statement('UPDATE themes SET is_default = 0');
        $db->update('themes', ['is_default' => 1, 'is_enabled' => 1], 'id = ?', [(int) $theme['id']]);
        SiteSettingsService::set('default_theme', $slug, 'appearance');
        self::log('theme.default', 'theme', (int) $theme['id'], $slug);
        return true;
    }

    public static function languages(): array
    {
        return self::db()->fetchAll('SELECT id, code, name, is_default, is_enabled FROM language_packs ORDER BY id');
    }

    public static function setLanguageEnabled(int $id, bool $enabled): bool
    {
        if (!self::db()->fetch('SELECT id FROM language_packs WHERE id = ? LIMIT 1', [$id])) {
            return false;
        }
        self::db()->update('language_packs', ['is_enabled' => $enabled ? 1 : 0], 'id = ?', [$id]);
        self::log('language.toggle', 'language', $id, $enabled ? 'on' : 'off');
        return true;
    }

    public static function setDefaultLanguage(string $code): bool
    {
        $row = self::db()->fetch('SELECT id FROM language_packs WHERE code = ? LIMIT 1', [$code]);
        if (!$row) {
            return false;
        }
        $db = self::db();
        $db->statement('UPDATE language_packs SET is_default = 0');
        $db->update('language_packs', ['is_default' => 1, 'is_enabled' => 1], 'id = ?', [(int) $row['id']]);
        SiteSettingsService::set('default_language', $code, 'i18n');
        self::log('language.default', 'language', (int) $row['id'], $code);
        return true;
    }

    // ==================================================================
    // 日志
    // ==================================================================

    public static function adminLogs(int $page = 1, string $keyword = ''): array
    {
        $db = self::db();
        $where = '1 = 1';
        $params = [];
        if ($keyword !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $where = '(l.action LIKE ? OR l.detail LIKE ? OR u.username LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id WHERE $where", $params);
        $items = $db->fetchAll(
            "SELECT l.id, l.action, l.target_type, l.target_id, l.detail, l.ip, l.created_at,
                    u.username, u.nickname
             FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id
             WHERE $where ORDER BY l.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function loginLogs(array $filters = [], int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['result'])) {
            $where[] = 'l.result = ?';
            $params[] = (string) $filters['result'];
        }
        if (!empty($filters['ip'])) {
            $where[] = 'l.ip = ?';
            $params[] = (string) $filters['ip'];
        }
        $w = implode(' AND ', $where);
        $perPage = self::PER_PAGE;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM login_logs l WHERE $w", $params);
        $items = $db->fetchAll(
            "SELECT l.id, l.user_id, l.ip, l.result, l.reason, l.created_at, u.username, u.nickname
             FROM login_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE $w ORDER BY l.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    // ==================================================================
    // WAF 日志（带筛选与分页）
    // ==================================================================

    public static function wafLogs(array $filters = [], int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['action'])) {
            $where[] = 'l.action = ?';
            $params[] = (string) $filters['action'];
        }
        if (!empty($filters['ip'])) {
            $where[] = 'l.ip = ?';
            $params[] = (string) $filters['ip'];
        }
        if (!empty($filters['min_score'])) {
            $where[] = 'l.score >= ?';
            $params[] = (int) $filters['min_score'];
        }
        $w = implode(' AND ', $where);
        $perPage = 30;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM waf_logs l WHERE $w", $params);
        $items = $db->fetchAll(
            "SELECT l.id, l.ip, l.user_id, l.uri, l.rule_name, l.score, l.action, l.user_agent, l.created_at
             FROM waf_logs l WHERE $w ORDER BY l.id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
