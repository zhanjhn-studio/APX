<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 动态业务服务：信息流、发布、编辑、删除、可见性校验、点赞、多级评论、转发、
 * 话题抽取与关联、收藏与通知聚合。权限校验（可见性）在此完成，不在 Controller。
 */
class PostService
{
    private const MEDIA_SUB = "(SELECT GROUP_CONCAT(CONCAT_WS('|', path, type, COALESCE(thumb_path,''), COALESCE(width,0), COALESCE(height,0), COALESCE(duration,0)) SEPARATOR '||') FROM post_media pm WHERE pm.post_id = p.id ORDER BY pm.sort ASC, pm.id ASC)";
    private const TOPIC_SUB = "(SELECT GROUP_CONCAT(CONCAT_WS('|', t.name, t.slug) SEPARATOR '||') FROM topics t JOIN post_topics pt ON pt.topic_id = t.id WHERE pt.post_id = p.id)";

    private static function db(): Database
    {
        return Database::instance();
    }

    /** 用户级屏蔽：屏蔽词（词表）与屏蔽用户（id 表），按请求缓存。 */
    private static ?array $muteCache = null;

    private static function muteRules(int $viewerId): array
    {
        if (self::$muteCache !== null) {
            return self::$muteCache;
        }
        if ($viewerId <= 0) {
            return self::$muteCache = ['words' => [], 'users' => []];
        }
        self::$muteCache = [
            'words' => SettingsService::mutedWords($viewerId),
            'users' => SettingsService::mutedUserIds($viewerId),
        ];
        return self::$muteCache;
    }

    /** 依据用户的屏蔽词 / 屏蔽用户过滤动态流。 */
    private static function applyMuteFilters(int $viewerId, array $posts): array
    {
        $rules = self::muteRules($viewerId);
        if (!$rules['words'] && !$rules['users']) {
            return $posts;
        }
        $out = [];
        foreach ($posts as $p) {
            if (in_array((int) ($p['user_id'] ?? 0), $rules['users'], true)) {
                continue;
            }
            if ($rules['words']) {
                $body = mb_strtolower((string) ($p['body'] ?? ''), 'UTF-8');
                $hit = false;
                foreach ($rules['words'] as $w) {
                    if ($w !== '' && mb_strpos($body, mb_strtolower($w, 'UTF-8')) !== false) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    continue;
                }
            }
            $out[] = $p;
        }
        return $out;
    }

    /**
     * 可见性感知信息流。游标分页（id 倒序）。
     * 可见范围：公开、自己、好友（friends）、密友（close_friends）；排除拉黑关系。
     */
    public static function getFeed(int $viewerId, int $before = 0, int $limit = 20): array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at, p.origin_post_id,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       op.id AS origin_id, op.body AS origin_body, op.visibility AS origin_visibility,
                       ou.username AS origin_username, ou.nickname AS origin_nickname, oup.avatar AS origin_avatar,
                       " . self::MEDIA_SUB . " AS media_csv,
                       " . self::TOPIC_SUB . " AS topics_csv
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                LEFT JOIN posts op ON op.id = p.origin_post_id
                LEFT JOIN users ou ON ou.id = op.user_id
                LEFT JOIN user_profiles oup ON oup.user_id = ou.id
                WHERE p.deleted_at IS NULL AND p.is_review = 0
                  AND (? = 0 OR p.id < ?)
                  AND (
                        p.visibility = 'public'
                        OR p.user_id = ?
                        OR (p.visibility = 'friends' AND EXISTS (
                                SELECT 1 FROM friendships f
                                WHERE (f.user_id = p.user_id AND f.friend_id = ?)
                                   OR (f.friend_id = p.user_id AND f.user_id = ?)
                            ))
                        OR (p.visibility = 'close_friends' AND EXISTS (
                                SELECT 1 FROM close_friends cf
                                WHERE cf.user_id = p.user_id AND cf.friend_id = ?
                            ))
                      )
                  AND NOT EXISTS (
                        SELECT 1 FROM blocks b
                        WHERE (b.user_id = p.user_id AND b.target_id = ?)
                           OR (b.user_id = ? AND b.target_id = p.user_id)
                      )
                ORDER BY p.id DESC
                LIMIT ?";
        return self::applyMuteFilters($viewerId, self::decorate(self::db()->fetchAll($sql, [
            $viewerId, $before, $before, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $limit,
        ])));
    }

    public static function getPost(int $postId, int $viewerId): ?array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at, p.origin_post_id,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       CASE WHEN fv.id IS NOT NULL THEN 1 ELSE 0 END AS favorited,
                       op.id AS origin_id, op.body AS origin_body, op.visibility AS origin_visibility,
                       ou.username AS origin_username, ou.nickname AS origin_nickname, oup.avatar AS origin_avatar
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                LEFT JOIN favorites fv ON fv.target_type = 'post' AND fv.target_id = p.id AND fv.user_id = ?
                LEFT JOIN posts op ON op.id = p.origin_post_id
                LEFT JOIN users ou ON ou.id = op.user_id
                LEFT JOIN user_profiles oup ON oup.user_id = ou.id
                WHERE p.id = ? AND p.deleted_at IS NULL
                LIMIT 1";
        $post = self::db()->fetch($sql, [$viewerId, $viewerId, $postId]);
        if (!$post) {
            return null;
        }
        if (!self::canView($post, $viewerId)) {
            return null;
        }
        $post['media'] = self::getPostMedia($postId);
        $post['topics'] = self::getPostTopics($postId);
        $post['comments'] = self::getCommentsTree($postId, $viewerId);
        return $post;
    }

    private static function canView(array $post, int $viewerId): bool
    {
        $vis = $post['visibility'];
        if ($vis === 'public') {
            return true;
        }
        if ((int) $post['user_id'] === $viewerId) {
            return true;
        }
        $db = self::db();
        if ($vis === 'friends') {
            return (bool) $db->fetch(
                'SELECT 1 FROM friendships f WHERE ((f.user_id = ? AND f.friend_id = ?) OR (f.friend_id = ? AND f.user_id = ?)) LIMIT 1',
                [$post['user_id'], $viewerId, $post['user_id'], $viewerId]
            );
        }
        if ($vis === 'close_friends') {
            return (bool) $db->fetch(
                'SELECT 1 FROM close_friends cf WHERE cf.user_id = ? AND cf.friend_id = ? LIMIT 1',
                [$post['user_id'], $viewerId]
            );
        }
        return false;
    }

    public static function getPostMedia(int $postId): array
    {
        return self::db()->fetchAll(
            'SELECT id, type, path, thumb_path, width, height, duration FROM post_media WHERE post_id = ? ORDER BY sort ASC, id ASC',
            [$postId]
        );
    }

    public static function getPostTopics(int $postId): array
    {
        return self::db()->fetchAll(
            "SELECT t.id, t.name, t.slug FROM topics t JOIN post_topics pt ON pt.topic_id = t.id WHERE pt.post_id = ? ORDER BY t.name ASC",
            [$postId]
        );
    }

    /** 个人主页动态列表（基线兼容）。 */
    public static function getUserPosts(int $ownerId, int $viewerId, int $before = 0, int $limit = 20): array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       " . self::MEDIA_SUB . " AS media_csv,
                       " . self::TOPIC_SUB . " AS topics_csv
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                WHERE p.user_id = ? AND p.deleted_at IS NULL AND p.is_review = 0
                  AND (? = 0 OR p.id < ?)
                  AND (
                    p.visibility = 'public'
                    OR ? = p.user_id
                    OR (p.visibility IN ('public','friends') AND ? IN (SELECT follower_id FROM follows WHERE following_id = p.user_id))
                    OR (p.visibility = 'close_friends' AND ? IN (SELECT user_id FROM friendships WHERE friend_id = p.user_id))
                  )
                ORDER BY p.id DESC
                LIMIT ?";
        return self::decorate(self::db()->fetchAll($sql, [$viewerId, $ownerId, $before, $before, $viewerId, $viewerId, $viewerId, $limit]));
    }

    public static function createPost(int $userId, string $body, string $visibility = 'public', array $media = [], ?int $originPostId = null): int
    {
        $visibility = in_array($visibility, ['public', 'friends', 'close_friends', 'private'], true) ? $visibility : 'public';
        $db = self::db();
        $db->beginTransaction();
        try {
            $id = $db->insert('posts', [
                'user_id'         => $userId,
                'body'            => $body,
                'visibility'      => $visibility,
                'origin_post_id'  => $originPostId,
                'created_at'      => now_utc(),
            ]);
            if ($media) {
                self::attachMedia($id, $media);
            }
            if ($body !== '' && $originPostId === null) {
                self::attachTopics($id, self::extractTopics($body));
            }
            self::notifyMentions($id, $userId, $body);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        AchievementService::award($userId, $originPostId === null ? 5 : 2, 'post');
        return $id;
    }

    public static function attachMedia(int $postId, array $media): void
    {
        $db = self::db();
        $sort = 0;
        foreach ($media as $m) {
            $db->insert('post_media', [
                'post_id'    => $postId,
                'type'       => $m['type'] === 'video' ? 'video' : 'image',
                'path'       => $m['path'],
                'thumb_path' => $m['thumb'] ?? null,
                'width'      => $m['w'] ?? null,
                'height'     => $m['h'] ?? null,
                'duration'   => $m['duration'] ?? null,
                'sort'       => $sort++,
                'created_at' => now_utc(),
            ]);
        }
    }

    /** 抽取正文中 #话题# 形式的标签。 */
    public static function extractTopics(string $body): array
    {
        $names = [];
        if (preg_match_all('/#([^\s#]{1,40})#/u', $body, $m)) {
            foreach ($m[1] as $raw) {
                $name = trim($raw);
                if ($name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }
        return $names;
    }

    /** 关联话题：不存在则创建，递增 post_count。 */
    public static function attachTopics(int $postId, array $names): void
    {
        if (!$names) {
            return;
        }
        $db = self::db();
        foreach ($names as $name) {
            $topic = $db->fetch('SELECT id FROM topics WHERE name = ? LIMIT 1', [$name]);
            if (!$topic) {
                $tid = $db->insert('topics', [
                    'name'       => $name,
                    'slug'       => self::slugify($name),
                    'post_count' => 0,
                    'created_at' => now_utc(),
                ]);
            } else {
                $tid = (int) $topic['id'];
            }
            $db->insertIgnore('post_topics', ['post_id' => $postId, 'topic_id' => $tid, 'created_at' => now_utc()]);
            $db->update('topics', ['post_count' => (int) $db->column('SELECT post_count FROM topics WHERE id = ?', [$tid]) + 1], 'id = ?', [$tid]);
        }
    }

    private static function slugify(string $name): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $name);
        $slug = trim(mb_strtolower($slug, 'UTF-8'), '-');
        return $slug === '' ? ('t' . substr(md5($name), 0, 8)) : $slug;
    }

    public static function updatePost(int $postId, int $userId, string $body, string $visibility): bool
    {
        $post = self::db()->fetch('SELECT id, user_id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$postId]);
        if (!$post || (int) $post['user_id'] !== $userId) {
            return false;
        }
        $visibility = in_array($visibility, ['public', 'friends', 'close_friends', 'private'], true) ? $visibility : 'public';
        self::db()->update('posts', ['body' => $body, 'visibility' => $visibility, 'updated_at' => now_utc()], 'id = ?', [$postId]);
        // 重新关联话题
        $oldTopics = self::db()->fetchAll('SELECT topic_id FROM post_topics WHERE post_id = ?', [$postId]);
        self::db()->delete('post_topics', 'post_id = ?', [$postId]);
        foreach ($oldTopics as $ot) {
            self::db()->update('topics', ['post_count' => max(0, (int) self::db()->column('SELECT post_count FROM topics WHERE id = ?', [$ot['topic_id']]) - 1)], 'id = ?', [$ot['topic_id']]);
        }
        self::attachTopics($postId, self::extractTopics($body));
        return true;
    }

    public static function deletePost(int $postId, int $userId): bool
    {
        $post = self::db()->fetch('SELECT id, user_id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$postId]);
        if (!$post || (int) $post['user_id'] !== $userId) {
            return false;
        }
        self::db()->update('posts', ['deleted_at' => now_utc(), 'updated_at' => now_utc()], 'id = ?', [$postId]);
        return true;
    }

    /** 多级评论树（按 parent_id 嵌套）。 */
    public static function getCommentsTree(int $postId, int $viewerId): array
    {
        $rows = self::db()->fetchAll(
            "SELECT c.id, c.post_id, c.user_id, c.parent_id, c.reply_to_user_id, c.body, c.like_count, c.created_at,
                    u.username, u.nickname, up.avatar,
                    CASE WHEN cl.id IS NOT NULL THEN 1 ELSE 0 END AS liked
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN comment_likes cl ON cl.comment_id = c.id AND cl.user_id = ?
             WHERE c.post_id = ? AND c.deleted_at IS NULL
             ORDER BY c.id ASC",
            [$viewerId, $postId]
        );
        $map = [];
        $tree = [];
        foreach ($rows as &$r) {
            $r['children'] = [];
            $map[$r['id']] = &$r;
        }
        unset($r);
        foreach ($rows as &$r) {
            if ($r['parent_id'] && isset($map[$r['parent_id']])) {
                $map[$r['parent_id']]['children'][] = &$r;
            } else {
                $tree[] = &$r;
            }
        }
        unset($r);
        return $tree;
    }

    public static function getComments(int $postId, int $limit = 50): array
    {
        return self::db()->fetchAll(
            "SELECT c.id, c.post_id, c.user_id, c.parent_id, c.reply_to_user_id, c.body, c.created_at,
                    u.username, u.nickname, up.avatar
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE c.post_id = ? AND c.deleted_at IS NULL
             ORDER BY c.id ASC
             LIMIT ?",
            [$postId, $limit]
        );
    }

    public static function getComment(int $id): array
    {
        return (array) self::db()->fetch(
            "SELECT c.id, c.post_id, c.user_id, c.parent_id, c.reply_to_user_id, c.body, c.like_count, c.created_at,
                    u.username, u.nickname, up.avatar,
                    CASE WHEN cl.id IS NOT NULL THEN 1 ELSE 0 END AS liked
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN comment_likes cl ON cl.comment_id = c.id AND cl.user_id = ?
             WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1",
            [AuthService::userId(), $id]
        );
    }

    public static function addComment(int $postId, int $userId, string $body, ?int $parentId = null, ?int $replyTo = null): array
    {
        $db = self::db();
        $post = $db->fetch('SELECT id, user_id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$postId]);
        if (!$post) {
            throw new \InvalidArgumentException('post not found');
        }
        $id = $db->insert('post_comments', [
            'post_id'          => $postId,
            'user_id'          => $userId,
            'parent_id'        => $parentId,
            'reply_to_user_id' => $replyTo,
            'body'             => $body,
            'created_at'       => now_utc(),
        ]);
        $db->update('posts', ['comment_count' => (int) $db->column('SELECT comment_count FROM posts WHERE id = ?', [$postId]) + 1], 'id = ?', [$postId]);
        self::notifyComment($postId, $userId, $replyTo);
        AchievementService::award($userId, 2, 'comment');
        return self::getComment($id);
    }

    public static function toggleCommentLike(int $commentId, int $userId): array
    {
        $db = self::db();
        $exists = $db->fetch('SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ? LIMIT 1', [$commentId, $userId]);
        if ($exists) {
            $db->delete('comment_likes', 'comment_id = ? AND user_id = ?', [$commentId, $userId]);
            $liked = false;
        } else {
            $db->insert('comment_likes', ['comment_id' => $commentId, 'user_id' => $userId, 'created_at' => now_utc()]);
            $liked = true;
        }
        $count = (int) $db->column('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?', [$commentId]);
        $db->update('post_comments', ['like_count' => $count], 'id = ?', [$commentId]);
        return ['liked' => $liked, 'count' => $count];
    }

    public static function deleteComment(int $commentId, int $userId): bool
    {
        $db = self::db();
        $c = $db->fetch('SELECT c.id, c.user_id, c.post_id FROM post_comments c WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1', [$commentId]);
        if (!$c) {
            return false;
        }
        $post = $db->fetch('SELECT user_id FROM posts WHERE id = ? LIMIT 1', [$c['post_id']]);
        if ((int) $c['user_id'] !== $userId && $post && (int) $post['user_id'] !== $userId) {
            return false;
        }
        $db->update('post_comments', ['deleted_at' => now_utc()], 'id = ?', [$commentId]);
        $db->update('posts', ['comment_count' => max(0, (int) $db->column('SELECT comment_count FROM posts WHERE id = ?', [$c['post_id']]) - 1)], 'id = ?', [$c['post_id']]);
        return true;
    }

    /** 转发：生成一条引用源动态的新动态，并累加源动态转发数。 */
    public static function repost(int $postId, int $userId): int
    {
        $origin = self::db()->fetch('SELECT id, user_id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$postId]);
        if (!$origin) {
            throw new \InvalidArgumentException('post not found');
        }
        $newId = self::createPost($userId, '', 'public', [], $postId);
        self::share($postId, $userId, $newId);
        NotificationService::notify((int) $origin['user_id'], 'repost', $userId, 'notification.repost', $postId, 'post');
        return $newId;
    }

    public static function share(int $postId, int $userId, ?int $newPostId = null): int
    {
        $db = self::db();
        $db->insertIgnore('post_shares', [
            'post_id'        => $newPostId ?? $postId,
            'user_id'        => $userId,
            'origin_post_id' => $postId,
            'created_at'    => now_utc(),
        ]);
        $db->update('posts', ['share_count' => (int) $db->column('SELECT share_count FROM posts WHERE id = ?', [$postId]) + 1], 'id = ?', [$postId]);
        return (int) $db->column('SELECT share_count FROM posts WHERE id = ?', [$postId]);
    }

    public static function toggleLike(int $postId, int $userId): array
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $exists = $db->fetch('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1', [$postId, $userId]);
            if ($exists) {
                $db->delete('post_likes', 'post_id = ? AND user_id = ?', [$postId, $userId]);
                $db->update('posts', ['like_count' => max(0, self::likeCount($postId) - 1)], 'id = ?', [$postId]);
                $liked = false;
            } else {
                $db->insert('post_likes', ['post_id' => $postId, 'user_id' => $userId, 'created_at' => now_utc()]);
                $db->update('posts', ['like_count' => self::likeCount($postId) + 1], 'id = ?', [$postId]);
                $liked = true;
                self::notifyLike($postId, $userId);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return ['liked' => $liked, 'count' => self::likeCount($postId)];
    }

    public static function toggleFavorite(int $postId, int $userId, ?int $folderId = null): bool
    {
        $db = self::db();
        $exists = $db->fetch("SELECT id, folder_id FROM favorites WHERE user_id = ? AND target_type = 'post' AND target_id = ? LIMIT 1", [$userId, $postId]);
        if ($exists) {
            if ($folderId === null) {
                $db->delete("favorites", "user_id = ? AND target_type = 'post' AND target_id = ?", [$userId, $postId]);
                $db->update('posts', ['favorite_count' => max(0, (int) $db->column('SELECT favorite_count FROM posts WHERE id = ?', [$postId]) - 1)], 'id = ?', [$postId]);
                return false;
            }
            $db->update('favorites', ['folder_id' => $folderId], 'id = ?', [(int) $exists['id']]);
            return true;
        }
        $db->insert('favorites', ['user_id' => $userId, 'target_type' => 'post', 'target_id' => $postId, 'folder_id' => $folderId, 'created_at' => now_utc()]);
        $db->update('posts', ['favorite_count' => (int) $db->column('SELECT favorite_count FROM posts WHERE id = ?', [$postId]) + 1], 'id = ?', [$postId]);
        return true;
    }

    private static function likeCount(int $postId): int
    {
        return (int) self::db()->column('SELECT like_count FROM posts WHERE id = ?', [$postId]);
    }

    // ---- 发现页 / 话题 ----

    public static function getByTopic(string $slug, int $viewerId, int $before = 0, int $limit = 20): array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at, p.origin_post_id,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       " . self::MEDIA_SUB . " AS media_csv,
                       " . self::TOPIC_SUB . " AS topics_csv
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                JOIN post_topics pt ON pt.post_id = p.id
                JOIN topics t ON t.id = pt.topic_id AND t.slug = ?
                WHERE p.deleted_at IS NULL AND p.is_review = 0
                  AND (? = 0 OR p.id < ?)
                  AND (
                        p.visibility = 'public'
                        OR p.user_id = ?
                        OR (p.visibility = 'friends' AND EXISTS (
                                SELECT 1 FROM friendships f
                                WHERE (f.user_id = p.user_id AND f.friend_id = ?)
                                   OR (f.friend_id = p.user_id AND f.user_id = ?)
                            ))
                        OR (p.visibility = 'close_friends' AND EXISTS (
                                SELECT 1 FROM close_friends cf
                                WHERE cf.user_id = p.user_id AND cf.friend_id = ?
                            ))
                      )
                ORDER BY p.id DESC
                LIMIT ?";
        return self::applyMuteFilters($viewerId, self::decorate(self::db()->fetchAll($sql, [
            $viewerId, $slug, $before, $before, $viewerId, $viewerId, $viewerId, $viewerId, $limit,
        ])));
    }

    public static function getHotTopics(int $limit = 14): array
    {
        return self::db()->fetchAll(
            'SELECT id, name, slug, post_count, follower_count FROM topics ORDER BY post_count DESC, follower_count DESC LIMIT ?',
            [$limit]
        );
    }

    public static function getTopicBySlug(string $slug): ?array
    {
        return self::db()->fetch('SELECT id, name, slug, description, post_count, follower_count, hot_score FROM topics WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function isTopicFollowed(int $topicId, int $userId): bool
    {
        return (bool) self::db()->fetch('SELECT 1 FROM topic_follows WHERE user_id = ? AND topic_id = ? LIMIT 1', [$userId, $topicId]);
    }

    /** 关注/取消关注话题；返回最新的关注状态（true=已关注）。 */
    public static function followTopic(int $topicId, int $userId): bool
    {
        $db = self::db();
        $exists = $db->fetch('SELECT id FROM topic_follows WHERE user_id = ? AND topic_id = ? LIMIT 1', [$userId, $topicId]);
        if ($exists) {
            $db->delete('topic_follows', 'user_id = ? AND topic_id = ?', [$userId, $topicId]);
            $db->update('topics', ['follower_count' => max(0, (int) $db->column('SELECT follower_count FROM topics WHERE id = ?', [$topicId]) - 1)], 'id = ?', [$topicId]);
            return false;
        }
        $db->insert('topic_follows', ['user_id' => $userId, 'topic_id' => $topicId, 'created_at' => now_utc()]);
        $db->update('topics', ['follower_count' => (int) $db->column('SELECT follower_count FROM topics WHERE id = ?', [$topicId]) + 1], 'id = ?', [$topicId]);
        return true;
    }

    public static function getHotPosts(int $viewerId, int $limit = 12): array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       " . self::MEDIA_SUB . " AS media_csv,
                       " . self::TOPIC_SUB . " AS topics_csv
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                WHERE p.deleted_at IS NULL AND p.is_review = 0 AND p.visibility = 'public' AND p.origin_post_id IS NULL
                ORDER BY (p.like_count * 2 + p.share_count * 3 + p.comment_count) DESC, p.id DESC
                LIMIT ?";
        return self::decorate(self::db()->fetchAll($sql, [$viewerId, $limit]));
    }

    /** 热门动态（游标分页版），用于发现页无限滚动。 */
    public static function getHotPostsCursor(int $viewerId, int $before = 0, int $limit = 20): array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at, p.origin_post_id,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       " . self::MEDIA_SUB . " AS media_csv,
                       " . self::TOPIC_SUB . " AS topics_csv
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                WHERE p.deleted_at IS NULL AND p.is_review = 0 AND p.visibility = 'public' AND p.origin_post_id IS NULL
                  AND (? = 0 OR p.id < ?)
                ORDER BY (p.like_count * 2 + p.share_count * 3 + p.comment_count) DESC, p.id DESC
                LIMIT ?";
        return self::applyMuteFilters($viewerId, self::decorate(self::db()->fetchAll($sql, [$viewerId, $before, $before, $limit])));
    }

    /**
     * 动态搜索（已装饰 media / topics），可见性规则与 getFeed 一致。
     * $like 为已转义 %/\_ 的 LIKE 模式。
     */
    public static function searchPosts(string $like, int $viewerId, int $before = 0, int $limit = 20): array
    {
        $sql = "SELECT p.id, p.user_id, p.body, p.visibility, p.like_count, p.comment_count,
                       p.share_count, p.favorite_count, p.created_at,
                       u.username, u.nickname, up.avatar,
                       CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS liked,
                       " . self::MEDIA_SUB . " AS media_csv,
                       " . self::TOPIC_SUB . " AS topics_csv
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.user_id = ?
                LEFT JOIN post_topics pt ON pt.post_id = p.id
                LEFT JOIN topics t ON t.id = pt.topic_id AND t.name LIKE ?
                WHERE p.deleted_at IS NULL AND p.is_review = 0
                  AND (? = 0 OR p.id < ?)
                  AND (p.body LIKE ? OR t.name LIKE ?)
                  AND (
                        p.visibility = 'public'
                        OR p.user_id = ?
                        OR (p.visibility = 'friends' AND EXISTS (
                                SELECT 1 FROM friendships f
                                WHERE (f.user_id = p.user_id AND f.friend_id = ?)
                                   OR (f.friend_id = p.user_id AND f.user_id = ?)
                            ))
                        OR (p.visibility = 'close_friends' AND EXISTS (
                                SELECT 1 FROM close_friends cf
                                WHERE cf.user_id = p.user_id AND cf.friend_id = ?
                            ))
                      )
                  AND NOT EXISTS (
                        SELECT 1 FROM blocks b
                        WHERE (b.user_id = p.user_id AND b.target_id = ?)
                           OR (b.user_id = ? AND b.target_id = p.user_id)
                      )
                GROUP BY p.id
                ORDER BY p.id DESC
                LIMIT ?";
        return self::applyMuteFilters($viewerId, self::decorate(self::db()->fetchAll($sql, [
            $viewerId, $like, $before, $before, $like, $like, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $limit,
        ])));
    }

    public static function getActiveUsers(int $viewerId, int $limit = 8): array
    {
        return self::db()->fetchAll(
            "SELECT u.id, u.username, u.nickname, up.avatar, up.bio
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id != ? AND u.status = 'active'
               AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
             ORDER BY u.last_active_at DESC, u.id DESC
             LIMIT ?",
            [$viewerId, $viewerId, $limit]
        );
    }

    public static function getNewUsers(int $viewerId, int $limit = 8): array
    {
        return self::db()->fetchAll(
            "SELECT u.id, u.username, u.nickname, up.avatar, up.bio
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id != ? AND u.status = 'active'
               AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
             ORDER BY u.created_at DESC
             LIMIT ?",
            [$viewerId, $viewerId, $limit]
        );
    }

    public static function getRecommendedUsers(int $viewerId, int $limit = 5): array
    {
        return self::db()->fetchAll(
            "SELECT u.id, u.username, u.nickname, up.avatar, up.bio
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id != ? AND u.status = 'active'
               AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
             ORDER BY u.created_at DESC
             LIMIT ?",
            [$viewerId, $viewerId, $limit]
        );
    }

    public static function getUnreadNotifications(int $userId, int $limit = 8): array
    {
        return self::db()->fetchAll(
            "SELECT n.id, n.type, n.actor_id, n.body, n.read_at, n.created_at,
                    u.username, u.nickname, up.avatar
             FROM notifications n
             LEFT JOIN users u ON u.id = n.actor_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) self::db()->column(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        );
    }

    // ---------------------------------------------------------------------
    // 装饰与通知辅助
    // ---------------------------------------------------------------------

    private static function decorate(array $posts): array
    {
        foreach ($posts as &$p) {
            $p['media'] = self::parseMedia($p['media_csv'] ?? '');
            $p['topics'] = self::parseTopics($p['topics_csv'] ?? '');
            unset($p['media_csv'], $p['topics_csv']);
        }
        unset($p);
        return $posts;
    }

    private static function parseMedia(string $csv): array
    {
        if ($csv === '') {
            return [];
        }
        $out = [];
        foreach (explode('||', $csv) as $row) {
            if ($row === '') {
                continue;
            }
            $cols = explode('|', $row);
            $out[] = [
                'type'     => $cols[1] ?? 'image',
                'path'     => $cols[0] ?? '',
                'thumb'    => $cols[2] ?? '',
                'w'        => (int) ($cols[3] ?? 0),
                'h'        => (int) ($cols[4] ?? 0),
                'duration' => (int) ($cols[5] ?? 0),
            ];
        }
        return $out;
    }

    private static function parseTopics(string $csv): array
    {
        if ($csv === '') {
            return [];
        }
        $out = [];
        foreach (explode('||', $csv) as $row) {
            if ($row === '') {
                continue;
            }
            [$name, $slug] = explode('|', $row) + [null, null];
            if ($name) {
                $out[] = ['name' => $name, 'slug' => $slug];
            }
        }
        return $out;
    }

    private static function notifyLike(int $postId, int $actorId): void
    {
        $post = self::db()->fetch('SELECT user_id FROM posts WHERE id = ?', [$postId]);
        if (!$post || (int) $post['user_id'] === $actorId) {
            return;
        }
        NotificationService::notify((int) $post['user_id'], 'like', $actorId, 'notification.like', $postId, 'post');
    }

    private static function notifyComment(int $postId, int $actorId, ?int $replyTo): void
    {
        $post = self::db()->fetch('SELECT user_id FROM posts WHERE id = ?', [$postId]);
        if (!$post) {
            return;
        }
        $owner = (int) $post['user_id'];
        if ($owner !== $actorId) {
            NotificationService::notify($owner, 'comment', $actorId, 'notification.comment', $postId, 'post');
        }
        if ($replyTo && $replyTo !== $actorId && $replyTo !== $owner) {
            NotificationService::notify($replyTo, 'reply', $actorId, 'notification.reply', $postId, 'post');
        }
    }

    private static function notifyMentions(int $postId, int $actorId, string $body): void
    {
        if (!preg_match_all('/@([A-Za-z0-9_]{3,32})/', $body, $m)) {
            return;
        }
        $names = array_values(array_unique($m[1]));
        if (!$names) {
            return;
        }
        $ph = implode(',', array_fill(0, count($names), '?'));
        $rows = self::db()->fetchAll("SELECT id FROM users WHERE username IN ($ph) AND id != ? AND status = 'active'", array_merge($names, [$actorId]));
        foreach ($rows as $r) {
            NotificationService::notify((int) $r['id'], 'mention', $actorId, 'notification.mention', $postId, 'post');
        }
    }
}
