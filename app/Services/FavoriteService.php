<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 收藏与收藏夹服务：收藏夹 CRUD、收藏开关（含分类）、列表与计数维护。
 * 注意：toggle 签名需与 PostController::favorite 调用保持一致。
 */
class FavoriteService
{
    private static function db(): Database
    {
        return Database::instance();
    }

    /**
     * 收藏开关（针对动态）。存在则取消（维护 posts.favorite_count），
     * 不存在则新增；传入 folderId 时把收藏归入指定收藏夹。
     * 返回最新收藏状态（true=已收藏）。
     */
    public static function toggle(int $userId, int $postId, ?int $folderId = null): bool
    {
        $db = self::db();
        $exists = $db->fetch("SELECT id, folder_id FROM favorites WHERE user_id = ? AND target_type = 'post' AND target_id = ? LIMIT 1", [$userId, $postId]);
        if ($exists) {
            if ($folderId === null && $exists['folder_id'] === null) {
                $db->delete("favorites", "user_id = ? AND target_type = 'post' AND target_id = ?", [$userId, $postId]);
                $db->update('posts', ['favorite_count' => max(0, (int) $db->column('SELECT favorite_count FROM posts WHERE id = ?', [$postId]) - 1)], 'id = ?', [$postId]);
                return false;
            }
            $db->update('favorites', ['folder_id' => $folderId], 'id = ?', [(int) $exists['id']]);
            return true;
        }
        $db->insert('favorites', [
            'user_id'     => $userId,
            'target_type' => 'post',
            'target_id'   => $postId,
            'folder_id'   => $folderId,
            'created_at'  => now_utc(),
        ]);
        $db->update('posts', ['favorite_count' => (int) $db->column('SELECT favorite_count FROM posts WHERE id = ?', [$postId]) + 1], 'id = ?', [$postId]);
        return true;
    }

    public static function isFavorited(int $userId, int $postId): bool
    {
        return self::db()->fetch(
            "SELECT id FROM favorites WHERE user_id = ? AND target_type = 'post' AND target_id = ? LIMIT 1",
            [$userId, $postId]
        ) !== null;
    }

    public static function favoriteCount(int $postId): int
    {
        return (int) self::db()->column('SELECT favorite_count FROM posts WHERE id = ?', [$postId]);
    }

    /**
     * 收藏夹列表（不含默认夹），附带各夹内收藏数。
     */
    public static function folders(int $userId): array
    {
        return self::db()->fetchAll(
            'SELECT f.*, (SELECT COUNT(*) FROM favorites fa WHERE fa.folder_id = f.id) AS count
             FROM favorite_folders f WHERE f.user_id = ? ORDER BY f.sort ASC, f.id ASC',
            [$userId]
        );
    }

    public static function defaultCount(int $userId): int
    {
        return (int) self::db()->column(
            "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND folder_id IS NULL",
            [$userId]
        );
    }

    public static function createFolder(int $userId, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            $name = __('favorite.default');
        }
        return self::db()->insert('favorite_folders', [
            'user_id'    => $userId,
            'name'       => mb_substr($name, 0, 32),
            'created_at' => now_utc(),
        ]);
    }

    public static function renameFolder(int $userId, int $folderId, string $name): void
    {
        $db = self::db();
        $folder = $db->fetch('SELECT id FROM favorite_folders WHERE id = ? AND user_id = ?', [$folderId, $userId]);
        if (!$folder) {
            return;
        }
        $db->update('favorite_folders', ['name' => mb_substr(trim($name), 0, 32)], 'id = ?', [$folderId]);
    }

    /**
     * 删除收藏夹：其下收藏移入默认夹（folder_id 置空）后删除该夹。
     */
    public static function deleteFolder(int $userId, int $folderId): void
    {
        $db = self::db();
        $folder = $db->fetch('SELECT id FROM favorite_folders WHERE id = ? AND user_id = ?', [$folderId, $userId]);
        if (!$folder) {
            return;
        }
        $db->update('favorites', ['folder_id' => null], 'user_id = ? AND folder_id = ?', [$userId, $folderId]);
        $db->delete('favorite_folders', 'id = ? AND user_id = ?', [$folderId, $userId]);
    }

    /**
     * 收藏列表（游标分页），动态类附带正文与作者信息。
     */
    public static function list(int $userId, ?int $folderId, int $before = 0, int $limit = 20): array
    {
        $db = self::db();
        if ($folderId === null || $folderId === 0) {
            $rows = $db->fetchAll(
                "SELECT f.* FROM favorites f
                 WHERE f.user_id = ? AND f.folder_id IS NULL
                   AND (? = 0 OR f.id < ?)
                 ORDER BY f.id DESC LIMIT ?",
                [$userId, $before, $before, $limit]
            );
        } else {
            $rows = $db->fetchAll(
                "SELECT f.* FROM favorites f
                 WHERE f.user_id = ? AND f.folder_id = ?
                   AND (? = 0 OR f.id < ?)
                 ORDER BY f.id DESC LIMIT ?",
                [$userId, $folderId, $before, $before, $limit]
            );
        }
        foreach ($rows as &$row) {
            if ($row['target_type'] === 'post') {
                $row['post'] = PostService::getPost((int) $row['target_id'], $userId);
            } else {
                $row['post'] = null;
            }
        }
        unset($row);
        return $rows;
    }
}
