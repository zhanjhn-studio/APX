<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 关注（单向）业务。权限：不能关注自己、不能重复关注。
 */
class FollowService
{
    public static function isFollowing(int $follower, int $following): bool
    {
        return Database::instance()->fetch(
            'SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1',
            [$follower, $following]
        ) !== null;
    }

    public static function toggle(int $follower, int $following): bool
    {
        if ($follower === $following) {
            return false;
        }
        $db = Database::instance();
        if (self::isFollowing($follower, $following)) {
            $db->delete('follows', 'follower_id = ? AND following_id = ?', [$follower, $following]);
            return false;
        }
        $db->insert('follows', ['follower_id' => $follower, 'following_id' => $following, 'created_at' => now_utc()]);
        return true;
    }

    public static function followerCount(int $userId): int
    {
        return (int) Database::instance()->column('SELECT COUNT(*) FROM follows WHERE following_id = ?', [$userId]);
    }
}
