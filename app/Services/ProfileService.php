<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 个人主页服务：聚合用户资料、计数、与浏览者的关系、等级徽章成就、
 * 所属公开群组，以及其可见动态。
 */
class ProfileService
{
    private static function db(): Database
    {
        return Database::instance();
    }

    public static function getByUsername(string $username, int $viewerId): ?array
    {
        $db = self::db();
        $user = $db->fetch(
            "SELECT u.id, u.username, u.nickname, u.status, u.created_at, u.level, u.experience,
                    u.two_factor_enabled, u.email_verified_at, u.email,
                    up.avatar, up.cover, up.bio, up.gender, up.location, up.website,
                    up.verified_type, up.verified_note
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.username = ? AND u.status != 'banned'
             LIMIT 1",
            [$username]
        );
        if (!$user) {
            return null;
        }
        $uid = (int) $user['id'];

        $stats = [
            'posts'     => (int) $db->column('SELECT COUNT(*) FROM posts WHERE user_id = ? AND deleted_at IS NULL AND is_review = 0', [$uid]),
            'following' => (int) $db->column('SELECT COUNT(*) FROM follows WHERE follower_id = ?', [$uid]),
            'followers' => (int) $db->column('SELECT COUNT(*) FROM follows WHERE following_id = ?', [$uid]),
            'friends'   => (int) $db->column('SELECT COUNT(*) FROM friendships WHERE user_id = ?', [$uid]),
        ];

        $relation = [
            'is_self'      => $viewerId === $uid,
            'is_following' => $viewerId > 0 && $db->fetch('SELECT id FROM follows WHERE follower_id = ? AND following_id = ?', [$viewerId, $uid]) !== null,
            'is_friend'    => $viewerId > 0 && $db->fetch('SELECT id FROM friendships WHERE user_id = ? AND friend_id = ?', [$viewerId, $uid]) !== null,
            'is_blocked'   => $viewerId > 0 && $db->fetch('SELECT id FROM blocks WHERE user_id = ? AND target_id = ?', [$viewerId, $uid]) !== null,
        ];

        return [
            'user'        => $user,
            'stats'       => $stats,
            'relation'    => $relation,
            'achievement' => AchievementService::summary($uid, $user),
            'groups'      => GroupService::userGroups($uid, 12),
            'posts'       => PostService::getUserPosts($uid, $viewerId, 0, 20),
        ];
    }
}
