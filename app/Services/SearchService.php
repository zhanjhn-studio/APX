<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Services\PostService;

/**
 * 全局搜索：动态 / 用户 / 话题。使用预处理 LIKE（转义 % 与 _）做子串匹配，
 * MySQL 5.7 全版本可用；如需更高召回可后续叠加 FULLTEXT(ngram)。
 * 登录用户写入搜索历史。
 */
class SearchService
{
    private static function db(): Database
    {
        return Database::instance();
    }

    private static function like(string $q): string
    {
        $q = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        return '%' . $q . '%';
    }

    /**
     * 概览搜索：各类型取前若干条，用于搜索页首屏。
     */
    public static function overview(string $q, int $viewerId, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['posts' => [], 'users' => [], 'topics' => [], 'groups' => [], 'count' => 0];
        }
        $ttl = (int) (Config::get('search.cache_ttl') ?? 60);
        $key = 'search:overview:' . md5($q . '|' . $viewerId . '|' . $limit);
        return Cache::remember($key, $ttl, function () use ($q, $viewerId, $limit): array {
            $posts = self::searchPosts($q, $viewerId, 0, $limit);
            $users = self::searchUsers($q, $limit);
            $topics = self::searchTopics($q, $limit);
            $groups = self::searchGroups($q, $viewerId, $limit);
            return [
                'posts'  => $posts,
                'users'  => $users,
                'topics' => $topics,
                'groups' => $groups,
                'count'  => count($posts) + count($users) + count($topics) + count($groups),
            ];
        });
    }

    /** 群组搜索：排除隐藏群与已解散群。 */
    public static function searchGroups(string $q, int $viewerId, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = self::like($q);
        return self::db()->fetchAll(
            "SELECT g.id, g.name, g.slug, g.avatar, g.description, g.visibility, g.member_count,
                    EXISTS(SELECT 1 FROM group_members m WHERE m.group_id = g.id AND m.user_id = ?) AS is_member
             FROM groups g
             WHERE g.status = 'active' AND g.visibility <> 'hidden'
               AND (g.name LIKE ? OR g.description LIKE ?)
             ORDER BY g.member_count DESC, g.id DESC
             LIMIT ?",
            [$viewerId, $like, $like, $limit]
        );
    }

    /**
     * 实时搜索建议：用户 / 话题 / 群组各取少量，供输入框下拉。
     */
    public static function suggest(string $q, int $viewerId, int $limit = 6): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) > 64) {
            return ['users' => [], 'topics' => [], 'groups' => [], 'total' => 0];
        }
        $ttl = (int) (Config::get('search.cache_ttl') ?? 60);
        $key = 'search:suggest:' . md5($q . '|' . $viewerId . '|' . $limit);
        return Cache::remember($key, $ttl, function () use ($q, $viewerId, $limit): array {
            $users = self::searchUsers($q, $limit);
            $topics = self::searchTopics($q, $limit);
            $groups = self::searchGroups($q, $viewerId, $limit);
            return [
                'users'  => array_map(fn($u) => [
                    'id' => (int) $u['id'], 'username' => $u['username'],
                    'nickname' => $u['nickname'], 'avatar' => $u['avatar'] ?? '',
                ], $users),
                'topics' => array_map(fn($t) => [
                    'id' => (int) $t['id'], 'name' => $t['name'], 'slug' => $t['slug'],
                    'post_count' => (int) $t['post_count'],
                ], $topics),
                'groups' => array_map(fn($g) => [
                    'id' => (int) $g['id'], 'name' => $g['name'], 'slug' => $g['slug'],
                    'avatar' => $g['avatar'] ?? '', 'member_count' => (int) $g['member_count'],
                ], $groups),
                'total'  => count($users) + count($topics) + count($groups),
            ];
        });
    }

    /** 全站热门搜索词（按出现次数，取近期）。 */
    public static function hotKeywords(int $limit = 8): array
    {
        $ttl = (int) (Config::get('search.cache_ttl') ?? 60);
        return Cache::remember('search:hot:' . $limit, $ttl, function () use ($limit): array {
            return self::db()->fetchAll(
                "SELECT keyword, COUNT(*) AS times, MAX(created_at) AS last_at
                 FROM search_history
                 WHERE keyword <> '' AND created_at > ?
                 GROUP BY keyword
                 ORDER BY times DESC, last_at DESC
                 LIMIT ?",
                [date('Y-m-d H:i:s', time() - 30 * 86400), $limit]
            );
        });
    }

    public static function searchPosts(string $q, int $viewerId, int $before = 0, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = self::like($q);
        return PostService::searchPosts($like, $viewerId, $before, $limit);
    }

    public static function searchUsers(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = self::like($q);
        return self::db()->fetchAll(
            "SELECT u.id, u.username, u.nickname, up.avatar, up.bio,
                    CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN follows f ON f.following_id = u.id AND f.follower_id = ?
             WHERE u.status = 'active' AND (u.username LIKE ? OR u.nickname LIKE ?)
             ORDER BY u.id DESC LIMIT ?",
            [self::viewerGuess(), $like, $like, $limit]
        );
    }

    public static function searchTopics(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = self::like($q);
        return self::db()->fetchAll(
            'SELECT * FROM topics WHERE name LIKE ? ORDER BY post_count DESC, hot_score DESC LIMIT ?',
            [$like, $limit]
        );
    }

    /**
     * searchUsers 的 following 标记需要 viewer，但预览搜索时可能匿名；这里取当前登录用户。
     */
    private static function viewerGuess(): int
    {
        return (int) (\App\Services\AuthService::userId() ?? 0);
    }

    public static function recordHistory(int $userId, string $q, int $resultCount): void
    {
        if ($userId <= 0 || trim($q) === '') {
            return;
        }
        self::db()->insert('search_history', [
            'user_id'      => $userId,
            'keyword'      => mb_substr(trim($q), 0, 128),
            'result_count' => $resultCount,
            'created_at'   => now_utc(),
        ]);
    }

    public static function recentHistory(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }
        return self::db()->fetchAll(
            'SELECT keyword, MAX(created_at) AS last_at, COUNT(*) AS times
             FROM search_history WHERE user_id = ? GROUP BY keyword ORDER BY last_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public static function clearHistory(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        self::db()->delete('search_history', 'user_id = ?', [$userId]);
    }
}
