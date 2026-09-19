<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 话题服务：话题查询、关注关系维护与计数。
 * 关注相关计数直接在 topics.follower_count 上维护，与 PostService::followTopic 行为一致。
 */
class TopicService
{
    private static function db(): Database
    {
        return Database::instance();
    }

    public static function getBySlug(string $slug): ?array
    {
        return self::db()->fetch(
            'SELECT id, name, slug, description, post_count, follower_count, hot_score
             FROM topics WHERE slug = ? LIMIT 1',
            [$slug]
        );
    }

    public static function isFollowed(int $topicId, int $userId): bool
    {
        return (bool) self::db()->fetch(
            'SELECT 1 FROM topic_follows WHERE user_id = ? AND topic_id = ? LIMIT 1',
            [$userId, $topicId]
        );
    }

    public static function follow(int $topicId, int $userId): void
    {
        $db = self::db();
        $exists = $db->fetch(
            'SELECT id FROM topic_follows WHERE user_id = ? AND topic_id = ? LIMIT 1',
            [$userId, $topicId]
        );
        if ($exists) {
            return;
        }
        $db->insert('topic_follows', [
            'user_id'    => $userId,
            'topic_id'   => $topicId,
            'created_at' => now_utc(),
        ]);
        $db->update(
            'topics',
            ['follower_count' => (int) $db->column('SELECT follower_count FROM topics WHERE id = ?', [$topicId]) + 1],
            'id = ?',
            [$topicId]
        );
    }

    public static function unfollow(int $topicId, int $userId): void
    {
        $db = self::db();
        $exists = $db->fetch(
            'SELECT id FROM topic_follows WHERE user_id = ? AND topic_id = ? LIMIT 1',
            [$userId, $topicId]
        );
        if (!$exists) {
            return;
        }
        $db->delete('topic_follows', 'user_id = ? AND topic_id = ?', [$userId, $topicId]);
        $db->update(
            'topics',
            ['follower_count' => max(0, (int) $db->column('SELECT follower_count FROM topics WHERE id = ?', [$topicId]) - 1)],
            'id = ?',
            [$topicId]
        );
    }
}
