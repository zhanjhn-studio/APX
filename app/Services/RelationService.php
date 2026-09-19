<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 三层关系之二：好友（双向确认）+ 特别关注 + 备注 + 分组。
 * 关注（单向）在 FollowService；本服务只负责好友这一层。
 */
class RelationService
{
    /** 发起好友申请；若对方已有反向待处理申请则自动互为好友。 */
    public static function sendRequest(int $from, int $to, string $message = ''): string
    {
        if ($from === $to) {
            return 'self';
        }
        $db = Database::instance();
        // 已是好友
        if (self::isFriend($from, $to)) {
            return 'friend';
        }
        // 反向待处理 -> 自动接受
        $reverse = $db->fetch(
            'SELECT id FROM friend_requests WHERE from_user_id = ? AND to_user_id = ? AND status = ? LIMIT 1',
            [$to, $from, 'pending']
        );
        if ($reverse) {
            self::accept($reverse['id'], $to);
            return 'accepted';
        }
        $existing = $db->fetch(
            'SELECT id FROM friend_requests WHERE from_user_id = ? AND to_user_id = ? AND status = ? LIMIT 1',
            [$from, $to, 'pending']
        );
        if ($existing) {
            return 'pending';
        }
        $db->insert('friend_requests', [
            'from_user_id' => $from, 'to_user_id' => $to, 'message' => mb_substr($message, 0, 128), 'status' => 'pending', 'created_at' => now_utc(),
        ]);
        NotificationService::notify($to, 'friend_request', $from, 'notification.friend_request', null, 'friend_request');
        return 'pending';
    }

    public static function respond(int $requestId, int $to, string $action): string
    {
        $db = Database::instance();
        $req = $db->fetch(
            'SELECT * FROM friend_requests WHERE id = ? AND to_user_id = ? AND status = ? LIMIT 1',
            [$requestId, $to, 'pending']
        );
        if (!$req) {
            return 'not_found';
        }
        if ($action === 'accept') {
            self::accept($requestId, $to);
            return 'accepted';
        }
        $db->update('friend_requests', ['status' => 'declined'], 'id = ?', [$requestId]);
        return 'declined';
    }

    private static function accept(int $requestId, int $to): void
    {
        $db = Database::instance();
        $req = $db->fetch('SELECT * FROM friend_requests WHERE id = ? LIMIT 1', [$requestId]);
        if (!$req) {
            return;
        }
        $from = (int) $req['from_user_id'];
        $db->insertIgnore('friendships', ['user_id' => $from, 'friend_id' => $to, 'created_at' => now_utc()]);
        $db->insertIgnore('friendships', ['user_id' => $to, 'friend_id' => $from, 'created_at' => now_utc()]);
        $db->update('friend_requests', ['status' => 'accepted'], 'id = ?', [$requestId]);
        NotificationService::notify($from, 'friend_accepted', $to, 'notification.friend_accepted', null, 'friend');
        AchievementService::award($from, 3, 'friend');
        AchievementService::award($to, 3, 'friend');
    }

    public static function isFriend(int $a, int $b): bool
    {
        return Database::instance()->fetch(
            'SELECT id FROM friendships WHERE user_id = ? AND friend_id = ? LIMIT 1',
            [$a, $b]
        ) !== null;
    }

    public static function setSpecial(int $userId, int $friendId, bool $on): void
    {
        Database::instance()->update('friendships', ['is_special' => $on ? 1 : 0], 'user_id = ? AND friend_id = ?', [$userId, $friendId]);
    }

    public static function setRemark(int $userId, int $friendId, string $remark): void
    {
        Database::instance()->update('friendships', ['remark' => mb_substr($remark, 0, 32)], 'user_id = ? AND friend_id = ?', [$userId, $friendId]);
    }

    public static function setGroup(int $userId, int $friendId, ?int $groupId): void
    {
        Database::instance()->update('friendships', ['group_id' => $groupId], 'user_id = ? AND friend_id = ?', [$userId, $friendId]);
    }

    public static function removeFriend(int $a, int $b): void
    {
        $db = Database::instance();
        $db->delete('friendships', 'user_id = ? AND friend_id = ?', [$a, $b]);
        $db->delete('friendships', 'user_id = ? AND friend_id = ?', [$b, $a]);
    }

    public static function listFriends(int $userId, bool $special = false, int $limit = 50): array
    {
        $sql = "SELECT f.friend_id AS id, f.remark, f.is_special, u.username, u.nickname, up.avatar
                FROM friendships f
                JOIN users u ON u.id = f.friend_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                WHERE f.user_id = ?" . ($special ? ' AND f.is_special = 1' : '') . "
                ORDER BY f.is_special DESC, f.created_at DESC LIMIT ?";
        return Database::instance()->fetchAll($sql, [$userId, $limit]);
    }

    public static function incomingRequests(int $userId, int $limit = 30): array
    {
        return Database::instance()->fetchAll(
            "SELECT r.id, r.message, r.from_user_id AS user_id, u.username, u.nickname, up.avatar
             FROM friend_requests r
             JOIN users u ON u.id = r.from_user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE r.to_user_id = ? AND r.status = 'pending'
             ORDER BY r.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function outgoingRequests(int $userId, int $limit = 30): array
    {
        return Database::instance()->fetchAll(
            "SELECT r.id, r.to_user_id AS user_id, u.username, u.nickname, up.avatar
             FROM friend_requests r
             JOIN users u ON u.id = r.to_user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE r.from_user_id = ? AND r.status = 'pending'
             ORDER BY r.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    // 关注（单向）相关
    public static function followers(int $userId, int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            "SELECT f.follower_id AS id, u.username, u.nickname, up.avatar
             FROM follows f JOIN users u ON u.id = f.follower_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE f.following_id = ? ORDER BY f.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function following(int $userId, int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            "SELECT f.following_id AS id, u.username, u.nickname, up.avatar
             FROM follows f JOIN users u ON u.id = f.following_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE f.follower_id = ? ORDER BY f.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }
}
