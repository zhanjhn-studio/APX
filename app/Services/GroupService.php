<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 群组业务：群聊与群动态双形态。
 * 三级角色（owner/admin/member）、公开/私密加入、邀请与申请审批、公告、禁言、
 * 退出与解散。权限三重校验（成员归属 + 关系 + 角色）全部在本层完成。
 */
class GroupService
{
    public const ROLE_OWNER  = 'owner';
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_MEMBER = 'member';

    private static function db(): Database
    {
        return Database::instance();
    }

    // ------------------------------------------------------------------
    // 读取
    // ------------------------------------------------------------------

    /** 我加入的群组（含角色）。 */
    public static function myGroups(int $userId, int $limit = 50): array
    {
        return self::db()->fetchAll(
            "SELECT g.id, g.name, g.slug, g.avatar, g.description, g.visibility,
                    g.member_count, g.post_count, gm.role, gm.muted_until
             FROM group_members gm
             JOIN groups g ON g.id = gm.group_id
             WHERE gm.user_id = ? AND g.status = 'active'
             ORDER BY gm.joined_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /** 发现群组：公开群，按成员数热度排序，可关键词过滤。 */
    public static function discover(int $viewerId, string $keyword = '', int $limit = 24): array
    {
        $kw = trim($keyword);
        if ($kw !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $kw) . '%';
            return self::db()->fetchAll(
                "SELECT g.id, g.name, g.slug, g.avatar, g.description, g.visibility,
                        g.member_count, g.post_count,
                        EXISTS(SELECT 1 FROM group_members m WHERE m.group_id = g.id AND m.user_id = ?) AS is_member
                 FROM groups g
                 WHERE g.status = 'active' AND g.visibility = 'public' AND (g.name LIKE ? OR g.description LIKE ?)
                 ORDER BY g.member_count DESC, g.id DESC
                 LIMIT ?",
                [$viewerId, $like, $like, $limit]
            );
        }
        return self::db()->fetchAll(
            "SELECT g.id, g.name, g.slug, g.avatar, g.description, g.visibility,
                    g.member_count, g.post_count,
                    EXISTS(SELECT 1 FROM group_members m WHERE m.group_id = g.id AND m.user_id = ?) AS is_member
             FROM groups g
             WHERE g.status = 'active' AND g.visibility = 'public'
             ORDER BY g.member_count DESC, g.id DESC
             LIMIT ?",
            [$viewerId, $limit]
        );
    }

    /** 某用户公开可见的群组（个人主页展示）。 */
    public static function userGroups(int $ownerId, int $limit = 12): array
    {
        return self::db()->fetchAll(
            "SELECT g.id, g.name, g.slug, g.avatar, g.member_count
             FROM group_members gm
             JOIN groups g ON g.id = gm.group_id
             WHERE gm.user_id = ? AND g.status = 'active' AND g.visibility = 'public'
             ORDER BY gm.joined_at DESC
             LIMIT ?",
            [$ownerId, $limit]
        );
    }

    /** 群组详情 + 访问者角色与权限。 */
    public static function getBySlug(string $slug, int $viewerId): ?array
    {
        $group = self::db()->fetch(
            "SELECT g.*, u.username AS owner_username, u.nickname AS owner_nickname, up.avatar AS owner_avatar
             FROM groups g
             JOIN users u ON u.id = g.owner_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE g.slug = ? AND g.status = 'active'
             LIMIT 1",
            [$slug]
        );
        if (!$group) {
            return null;
        }
        $groupId = (int) $group['id'];
        $role = self::roleOf($groupId, $viewerId);
        $mutedUntil = null;
        if ($role !== null) {
            $row = self::db()->fetch('SELECT muted_until FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1', [$groupId, $viewerId]);
            $mutedUntil = $row['muted_until'] ?? null;
        }
        $pending = 0;
        if ($role === null) {
            $pending = (int) self::db()->column(
                "SELECT COUNT(*) FROM group_requests WHERE group_id = ? AND user_id = ? AND status = 'pending'",
                [$groupId, $viewerId]
            );
        }
        $reqCount = 0;
        if (self::can($role, 'manage')) {
            $reqCount = (int) self::db()->column("SELECT COUNT(*) FROM group_requests WHERE group_id = ? AND status = 'pending'", [$groupId]);
        }

        return [
            'group'       => $group,
            'role'        => $role,
            'is_member'   => $role !== null,
            'is_muted'    => $mutedUntil !== null && strtotime((string) $mutedUntil) > time(),
            'muted_until' => $mutedUntil,
            'is_pending'  => $pending > 0,
            'permissions' => [
                'manage'   => self::can($role, 'manage'),
                'announce' => self::can($role, 'announce'),
                'post'     => $role !== null && !self::isMuted($groupId, $viewerId),
                'chat'     => $role !== null && !self::isMuted($groupId, $viewerId),
                'invite'   => self::can($role, 'manage'),
                'kick'     => self::can($role, 'manage'),
            ],
            'request_count' => $reqCount,
        ];
    }

    public static function simple(int $groupId): ?array
    {
        return self::db()->fetch('SELECT id, owner_id, name, slug, avatar, visibility, status, member_count FROM groups WHERE id = ? LIMIT 1', [$groupId]);
    }

    // ------------------------------------------------------------------
    // 生命周期
    // ------------------------------------------------------------------

    public static function create(int $userId, string $name, string $description, string $visibility): int
    {
        $db = self::db();
        $name = mb_substr(trim($name), 0, 64);
        $slug = self::uniqueSlug($name);
        $visibility = in_array($visibility, ['public', 'private', 'hidden'], true) ? $visibility : 'public';

        $db->beginTransaction();
        try {
            $groupId = $db->insert('groups', [
                'owner_id'     => $userId,
                'name'         => $name,
                'slug'         => $slug,
                'description'  => mb_substr(trim($description), 0, 255),
                'avatar'       => '',
                'visibility'   => $visibility,
                'member_count' => 1,
                'post_count'   => 0,
                'status'       => 'active',
                'created_at'   => now_utc(),
            ]);
            $db->insert('group_members', [
                'group_id'  => $groupId,
                'user_id'   => $userId,
                'role'      => self::ROLE_OWNER,
                'joined_at' => now_utc(),
            ]);
            $db->insert('group_messages', [
                'group_id'   => $groupId,
                'sender_id'  => $userId,
                'type'       => 'system',
                'body'       => 'group.system.created',
                'created_at' => now_utc(),
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        AchievementService::award($userId, 10, 'group');
        return $groupId;
    }

    /** 更新群资料（需 manage 权限）。 */
    public static function update(int $groupId, int $userId, array $data): bool
    {
        if (!self::can(self::roleOf($groupId, $userId), 'manage')) {
            return false;
        }
        $fields = [];
        if (isset($data['name']) && trim((string) $data['name']) !== '') {
            $fields['name'] = mb_substr(trim((string) $data['name']), 0, 64);
        }
        if (isset($data['description'])) {
            $fields['description'] = mb_substr(trim((string) $data['description']), 0, 255);
        }
        if (isset($data['avatar'])) {
            $fields['avatar'] = mb_substr(trim((string) $data['avatar']), 0, 255);
        }
        if (isset($data['visibility']) && in_array($data['visibility'], ['public', 'private', 'hidden'], true)) {
            $fields['visibility'] = $data['visibility'];
        }
        if (!$fields) {
            return false;
        }
        self::db()->update('groups', $fields, 'id = ?', [$groupId]);
        return true;
    }

    /** 加入公开群立即成为成员；私密群生成待审批申请。 */
    public static function join(int $groupId, int $userId, string $message = ''): string
    {
        $db = self::db();
        $group = self::simple($groupId);
        if (!$group || $group['status'] !== 'active') {
            return 'not_found';
        }
        if (self::roleOf($groupId, $userId) !== null) {
            return 'already';
        }
        if ((int) $group['owner_id'] === $userId) {
            return 'already';
        }
        if ($group['visibility'] === 'public') {
            self::addMember($groupId, $userId, self::ROLE_MEMBER);
            return 'joined';
        }
        if ($group['visibility'] === 'hidden') {
            return 'not_found';
        }
        // private：申请审批
        $db->statement(
            "INSERT INTO group_requests (group_id, user_id, message, status, created_at)
             VALUES (?, ?, ?, 'pending', ?)
             ON DUPLICATE KEY UPDATE message = VALUES(message), status = 'pending', created_at = VALUES(created_at)",
            [$groupId, $userId, mb_substr($message, 0, 128), now_utc()]
        );
        $admins = $db->fetchAll("SELECT user_id FROM group_members WHERE group_id = ? AND role IN ('owner','admin')", [$groupId]);
        foreach ($admins as $a) {
            NotificationService::notify((int) $a['user_id'], 'group_invite', $userId, 'notification.group_request', $groupId, 'group');
        }
        return 'requested';
    }

    public static function leave(int $groupId, int $userId): bool
    {
        $role = self::roleOf($groupId, $userId);
        if ($role === null) {
            return false;
        }
        if ($role === self::ROLE_OWNER) {
            return false; // 群主须先转让或解散，不能直接退出
        }
        self::db()->delete('group_members', 'group_id = ? AND user_id = ?', [$groupId, $userId]);
        self::recount($groupId);
        self::db()->insert('group_messages', [
            'group_id'   => $groupId,
            'sender_id'  => $userId,
            'type'       => 'system',
            'body'       => 'group.system.left',
            'created_at' => now_utc(),
        ]);
        return true;
    }

    public static function disband(int $groupId, int $userId): bool
    {
        $group = self::simple($groupId);
        if (!$group || (int) $group['owner_id'] !== $userId) {
            return false;
        }
        self::db()->update('groups', ['status' => 'disbanded'], 'id = ?', [$groupId]);
        return true;
    }

    /** 群主转让（移交所有权，原群主降为管理员）。 */
    public static function transferOwner(int $groupId, int $userId, int $targetId): bool
    {
        $group = self::simple($groupId);
        if (!$group || (int) $group['owner_id'] !== $userId) {
            return false;
        }
        if (self::roleOf($groupId, $targetId) === null) {
            return false;
        }
        $db = self::db();
        $db->update('group_members', ['role' => self::ROLE_ADMIN], 'group_id = ? AND user_id = ?', [$groupId, $userId]);
        $db->update('group_members', ['role' => self::ROLE_OWNER], 'group_id = ? AND user_id = ?', [$groupId, $targetId]);
        $db->update('groups', ['owner_id' => $targetId], 'id = ?', [$groupId]);
        return true;
    }

    // ------------------------------------------------------------------
    // 成员 / 申请 / 邀请
    // ------------------------------------------------------------------

    public static function addMember(int $groupId, int $userId, string $role = self::ROLE_MEMBER): void
    {
        $db = self::db();
        $db->statement(
            "INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE role = role",
            [$groupId, $userId, $role, now_utc()]
        );
        // 直接被拉入的路径：清掉未决申请
        $db->delete('group_requests', "group_id = ? AND user_id = ? AND status = 'pending'", [$groupId, $userId]);
        self::recount($groupId);
    }

    public static function members(int $groupId, int $limit = 200): array
    {
        return self::db()->fetchAll(
            "SELECT gm.user_id AS id, gm.role, gm.muted_until, gm.joined_at,
                    u.username, u.nickname, up.avatar
             FROM group_members gm
             JOIN users u ON u.id = gm.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE gm.group_id = ?
             ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), gm.joined_at ASC
             LIMIT ?",
            [$groupId, $limit]
        );
    }

    public static function requests(int $groupId, int $userId): array
    {
        if (!self::can(self::roleOf($groupId, $userId), 'manage')) {
            return [];
        }
        return self::db()->fetchAll(
            "SELECT r.id, r.user_id, r.message, r.created_at, u.username, u.nickname, up.avatar
             FROM group_requests r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE r.group_id = ? AND r.status = 'pending'
             ORDER BY r.created_at ASC",
            [$groupId]
        );
    }

    public static function respondRequest(int $requestId, int $userId, string $action): bool
    {
        $db = self::db();
        $req = $db->fetch("SELECT id, group_id, user_id FROM group_requests WHERE id = ? AND status = 'pending' LIMIT 1", [$requestId]);
        if (!$req) {
            return false;
        }
        $groupId = (int) $req['group_id'];
        if (!self::can(self::roleOf($groupId, $userId), 'manage')) {
            return false;
        }
        $applicant = (int) $req['user_id'];
        if ($action === 'accept') {
            $db->update('group_requests', ['status' => 'accepted'], 'id = ?', [$requestId]);
            self::addMember($groupId, $applicant, self::ROLE_MEMBER);
            NotificationService::notify($applicant, 'group_invite', $userId, 'notification.group_accepted', $groupId, 'group');
            return true;
        }
        $db->update('group_requests', ['status' => 'declined'], 'id = ?', [$requestId]);
        return true;
    }

    /** 直接邀请加入（群主/管理员）。 */
    public static function invite(int $groupId, int $userId, int $targetId): bool
    {
        if ($targetId <= 0 || $targetId === $userId) {
            return false;
        }
        if (!self::can(self::roleOf($groupId, $userId), 'manage')) {
            return false;
        }
        if (self::roleOf($groupId, $targetId) !== null) {
            return false;
        }
        $exists = self::db()->fetch('SELECT id FROM users WHERE id = ? AND status = ? LIMIT 1', [$targetId, 'active']);
        if (!$exists) {
            return false;
        }
        self::addMember($groupId, $targetId, self::ROLE_MEMBER);
        NotificationService::notify($targetId, 'group_invite', $userId, 'notification.group_invited', $groupId, 'group');
        return true;
    }

    public static function kick(int $groupId, int $userId, int $targetId): bool
    {
        $db = self::db();
        $myRole = self::roleOf($groupId, $userId);
        $targetRole = self::roleOf($groupId, $targetId);
        if (!self::can($myRole, 'manage') || $targetRole === null) {
            return false;
        }
        if ($targetRole === self::ROLE_OWNER) {
            return false;
        }
        if ($myRole === self::ROLE_ADMIN && $targetRole === self::ROLE_ADMIN) {
            return false; // 管理员之间不能互相移除
        }
        $db->delete('group_members', 'group_id = ? AND user_id = ?', [$groupId, $targetId]);
        self::recount($groupId);
        return true;
    }

    public static function setRole(int $groupId, int $userId, int $targetId, string $role): bool
    {
        if ($role !== self::ROLE_ADMIN && $role !== self::ROLE_MEMBER) {
            return false;
        }
        $group = self::simple($groupId);
        if (!$group || (int) $group['owner_id'] !== $userId) {
            return false; // 仅群主可任免管理员
        }
        if (self::roleOf($groupId, $targetId) === null) {
            return false;
        }
        self::db()->update('group_members', ['role' => $role], 'group_id = ? AND user_id = ?', [$groupId, $targetId]);
        return true;
    }

    /** 禁言 minutes 分钟（<=0 表示解除）。 */
    public static function setMute(int $groupId, int $userId, int $targetId, int $minutes): bool
    {
        $myRole = self::roleOf($groupId, $userId);
        $targetRole = self::roleOf($groupId, $targetId);
        if (!self::can($myRole, 'manage') || $targetRole === null || $targetRole === self::ROLE_OWNER) {
            return false;
        }
        if ($myRole === self::ROLE_ADMIN && $targetRole === self::ROLE_ADMIN) {
            return false;
        }
        $until = $minutes > 0 ? date('Y-m-d H:i:s', time() + $minutes * 60) : null;
        self::db()->update('group_members', ['muted_until' => $until], 'group_id = ? AND user_id = ?', [$groupId, $targetId]);
        return true;
    }

    public static function isMuted(int $groupId, int $userId): bool
    {
        $row = self::db()->fetch('SELECT muted_until FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1', [$groupId, $userId]);
        return !empty($row['muted_until']) && strtotime((string) $row['muted_until']) > time();
    }

    // ------------------------------------------------------------------
    // 公告
    // ------------------------------------------------------------------

    public static function announcements(int $groupId, int $limit = 30): array
    {
        return self::db()->fetchAll(
            "SELECT a.id, a.user_id, a.body, a.is_pinned, a.created_at, u.username, u.nickname, up.avatar
             FROM group_announcements a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE a.group_id = ?
             ORDER BY a.is_pinned DESC, a.id DESC
             LIMIT ?",
            [$groupId, $limit]
        );
    }

    public static function createAnnouncement(int $groupId, int $userId, string $body, bool $pinned): int
    {
        if (!self::can(self::roleOf($groupId, $userId), 'announce')) {
            throw new \RuntimeException('permission.denied');
        }
        $body = mb_substr(trim($body), 0, 2000);
        if ($body === '') {
            throw new \InvalidArgumentException('validation.required');
        }
        $id = self::db()->insert('group_announcements', [
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'body'       => $body,
            'is_pinned'  => $pinned ? 1 : 0,
            'created_at' => now_utc(),
        ]);
        $members = self::db()->fetchAll('SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ?', [$groupId, $userId]);
        foreach ($members as $m) {
            NotificationService::notify((int) $m['user_id'], 'system', $userId, 'notification.group_announcement', $groupId, 'group');
        }
        return $id;
    }

    public static function deleteAnnouncement(int $groupId, int $userId, int $id): bool
    {
        if (!self::can(self::roleOf($groupId, $userId), 'announce')) {
            return false;
        }
        $row = self::db()->fetch('SELECT id FROM group_announcements WHERE id = ? AND group_id = ? LIMIT 1', [$id, $groupId]);
        if (!$row) {
            return false;
        }
        self::db()->delete('group_announcements', 'id = ?', [$id]);
        return true;
    }

    public static function pinAnnouncement(int $groupId, int $userId, int $id, bool $pinned): bool
    {
        if (!self::can(self::roleOf($groupId, $userId), 'announce')) {
            return false;
        }
        $row = self::db()->fetch('SELECT id FROM group_announcements WHERE id = ? AND group_id = ? LIMIT 1', [$id, $groupId]);
        if (!$row) {
            return false;
        }
        self::db()->update('group_announcements', ['is_pinned' => $pinned ? 1 : 0], 'id = ?', [$id]);
        return true;
    }

    // ------------------------------------------------------------------
    // 群动态（形态一）
    // ------------------------------------------------------------------

    public static function posts(int $groupId, int $viewerId, int $before = 0, int $limit = 20): array
    {
        $rows = self::db()->fetchAll(
            "SELECT gp.id, gp.group_id, gp.user_id, gp.body, gp.visibility, gp.like_count, gp.comment_count, gp.created_at,
                    u.username, u.nickname, up.avatar
             FROM group_posts gp
             JOIN users u ON u.id = gp.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE gp.group_id = ? AND gp.deleted_at IS NULL
               AND (? = 0 OR gp.id < ?)
             ORDER BY gp.id DESC
             LIMIT ?",
            [$groupId, $before, $before, $limit]
        );
        $liked = self::likedIds($viewerId, $groupId);
        foreach ($rows as &$r) {
            $r['liked'] = in_array((string) $r['id'], $liked, true);
        }
        unset($r);
        return $rows;
    }

    /**
     * 当前用户在某群内点过赞的群动态 id（沿用 user_settings 存储，上限 300 条，避免新增数据表）。
     */
    private static function likedIds(int $userId, int $groupId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stored = (string) (SettingsService::get($userId, 'gpost_likes:' . $groupId, '') ?? '');
        if ($stored === '') {
            return [];
        }
        return array_values(array_filter(explode(',', $stored), fn($v) => $v !== ''));
    }

    public static function createPost(int $groupId, int $userId, string $body): int
    {
        if (self::roleOf($groupId, $userId) === null) {
            throw new \RuntimeException('permission.denied');
        }
        if (self::isMuted($groupId, $userId)) {
            throw new \RuntimeException('group.muted');
        }
        $body = mb_substr(trim($body), 0, 4000);
        if ($body === '') {
            throw new \InvalidArgumentException('validation.required');
        }
        $db = self::db();
        $id = $db->insert('group_posts', [
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'body'       => $body,
            'visibility' => 'members',
            'created_at' => now_utc(),
        ]);
        $db->statement('UPDATE `groups` SET `post_count` = `post_count` + 1 WHERE `id` = ?', [$groupId]);
        return $id;
    }

    public static function deletePost(int $groupId, int $userId, int $postId): bool
    {
        $db = self::db();
        $post = $db->fetch('SELECT id, user_id FROM group_posts WHERE id = ? AND group_id = ? AND deleted_at IS NULL LIMIT 1', [$postId, $groupId]);
        if (!$post) {
            return false;
        }
        $isAuthor = (int) $post['user_id'] === $userId;
        if (!$isAuthor && !self::can(self::roleOf($groupId, $userId), 'manage')) {
            return false;
        }
        $db->update('group_posts', ['deleted_at' => now_utc()], 'id = ?', [$postId]);
        $db->statement('UPDATE `groups` SET `post_count` = GREATEST(0, `post_count` - 1) WHERE `id` = ?', [$groupId]);
        return true;
    }

    /** 群动态点赞 / 取消点赞（按用户去重，沿用 user_settings 记录已赞 id）。 */
    public static function likePost(int $groupId, int $postId, int $userId): array
    {
        $db = self::db();
        $post = $db->fetch('SELECT id, like_count FROM group_posts WHERE id = ? AND group_id = ? AND deleted_at IS NULL LIMIT 1', [$postId, $groupId]);
        if (!$post) {
            return ['liked' => false, 'count' => 0];
        }
        $key = 'gpost_likes:' . $groupId;
        $ids = self::likedIds($userId, $groupId);
        $idStr = (string) $postId;
        if (in_array($idStr, $ids, true)) {
            $ids = array_values(array_diff($ids, [$idStr]));
            $delta = -1;
            $liked = false;
        } else {
            $ids[] = $idStr;
            if (count($ids) > 300) {
                $ids = array_slice($ids, -300);
            }
            $delta = 1;
            $liked = true;
        }
        SettingsService::set($userId, $key, implode(',', $ids));
        $count = max(0, (int) $post['like_count'] + $delta);
        $db->update('group_posts', ['like_count' => $count], 'id = ?', [$postId]);
        return ['liked' => $liked, 'count' => $count];
    }

    // ------------------------------------------------------------------
    // 群聊（形态二）
    // ------------------------------------------------------------------

    public static function messages(int $groupId, int $viewerId, int $before = 0, int $limit = 40): array
    {
        if (self::roleOf($groupId, $viewerId) === null) {
            return [];
        }
        return self::db()->fetchAll(
            "SELECT m.id, m.sender_id, m.type, m.body, m.reply_to_id, m.recalled_at, m.created_at,
                    u.username, u.nickname, up.avatar
             FROM group_messages m
             LEFT JOIN users u ON u.id = m.sender_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE m.group_id = ? AND (? = 0 OR m.id < ?)
             ORDER BY m.id DESC
             LIMIT ?",
            [$groupId, $before, $before, $limit]
        );
    }

    public static function sendMessage(int $groupId, int $userId, string $body, ?int $replyTo = null, string $type = 'text'): array
    {
        if (self::roleOf($groupId, $userId) === null) {
            throw new \RuntimeException('permission.denied');
        }
        if (self::isMuted($groupId, $userId)) {
            throw new \RuntimeException('group.muted');
        }
        $body = mb_substr(trim($body), 0, 4000);
        if ($body === '') {
            throw new \InvalidArgumentException('validation.required');
        }
        if (!in_array($type, ['text', 'emoji', 'image', 'file', 'location'], true)) {
            $type = 'text';
        }
        $db = self::db();
        $id = $db->insert('group_messages', [
            'group_id'    => $groupId,
            'sender_id'   => $userId,
            'type'        => $type,
            'body'        => $body,
            'reply_to_id' => $replyTo,
            'created_at'  => now_utc(),
        ]);
        return (array) $db->fetch(
            "SELECT m.id, m.sender_id, m.type, m.body, m.reply_to_id, m.recalled_at, m.created_at,
                    u.username, u.nickname, up.avatar
             FROM group_messages m
             LEFT JOIN users u ON u.id = m.sender_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE m.id = ? LIMIT 1",
            [$id]
        );
    }

    public static function recallMessage(int $groupId, int $userId, int $messageId): bool
    {
        $db = self::db();
        $msg = $db->fetch('SELECT id, sender_id, created_at FROM group_messages WHERE id = ? AND group_id = ? LIMIT 1', [$messageId, $groupId]);
        if (!$msg) {
            return false;
        }
        $isAuthor = (int) $msg['sender_id'] === $userId;
        $isManager = self::can(self::roleOf($groupId, $userId), 'manage');
        if (!$isAuthor && !$isManager) {
            return false;
        }
        if ($isAuthor && !$isManager && time() - strtotime((string) $msg['created_at']) > 120) {
            return false; // 超过 2 分钟撤回窗口
        }
        $db->update('group_messages', ['recalled_at' => now_utc(), 'type' => 'recall', 'body' => ''], 'id = ?', [$messageId]);
        return true;
    }

    // ------------------------------------------------------------------
    // 权限与工具
    // ------------------------------------------------------------------

    public static function roleOf(int $groupId, int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        $row = self::db()->fetch('SELECT role FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1', [$groupId, $userId]);
        return $row ? (string) $row['role'] : null;
    }

    /** 角色能力判定：manage（管理成员/资料/审批）、announce（公告）。 */
    public static function can(?string $role, string $capability): bool
    {
        if ($role === null) {
            return false;
        }
        if ($role === self::ROLE_OWNER) {
            return true;
        }
        if ($role === self::ROLE_ADMIN) {
            return in_array($capability, ['manage', 'announce'], true);
        }
        return false;
    }

    public static function recount(int $groupId): void
    {
        $db = self::db();
        $members = (int) $db->column('SELECT COUNT(*) FROM group_members WHERE group_id = ?', [$groupId]);
        $posts = (int) $db->column('SELECT COUNT(*) FROM group_posts WHERE group_id = ? AND deleted_at IS NULL', [$groupId]);
        $db->update('groups', ['member_count' => $members, 'post_count' => $posts], 'id = ?', [$groupId]);
    }

    public static function byId(int $groupId): ?array
    {
        return self::db()->fetch('SELECT * FROM groups WHERE id = ? LIMIT 1', [$groupId]);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = preg_replace('/[^\p{L}\p{N}]+/u', '-', $name);
        $base = trim(mb_strtolower((string) $base, 'UTF-8'), '-');
        $base = mb_substr($base, 0, 48);
        if ($base === '') {
            $base = 'g-' . substr(md5($name . microtime(true)), 0, 8);
        }
        $slug = $base;
        $i = 1;
        while (self::db()->fetch('SELECT id FROM groups WHERE slug = ? LIMIT 1', [$slug])) {
            $slug = $base . '-' . (++$i);
            if ($i > 200) {
                $slug = $base . '-' . substr(md5((string) microtime(true)), 0, 6);
                break;
            }
        }
        return $slug;
    }
}
