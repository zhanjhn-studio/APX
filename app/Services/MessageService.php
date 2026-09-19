<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * 私聊 / 群聊业务。会话成员、消息、撤回（2 分钟）、引用回复、已读回执、表情回应、
 * 阅后即焚、位置消息、图片/文件附件、置顶/免打扰/删除会话、会话内搜索与「正在输入」。
 */
class MessageService
{
    /** 引用回复预览的公共 SELECT 片段（需与 messages() 一致）。 */
    private const REPLY_SELECT = "rp.body AS reply_body, rp.type AS reply_type, rp.sender_id AS reply_sender,
                                 ru.username AS reply_username, ru.nickname AS reply_nickname";

    private static function db(): Database
    {
        return Database::instance();
    }

    /** 取或创建两人私聊会话。 */
    public static function getOrCreateDirect(int $a, int $b): int
    {
        $conv = self::db()->fetch(
            "SELECT c.id FROM conversations c
             JOIN conversation_members cm ON cm.conversation_id = c.id
             WHERE c.type = 'direct' AND cm.user_id IN (?, ?)
             GROUP BY c.id HAVING COUNT(DISTINCT cm.user_id) = 2
             LIMIT 1",
            [$a, $b]
        );
        if ($conv) {
            return (int) $conv['id'];
        }
        $db = self::db();
        $id = $db->insert('conversations', ['type' => 'direct', 'created_at' => now_utc()]);
        $db->insert('conversation_members', ['conversation_id' => $id, 'user_id' => $a, 'created_at' => now_utc()]);
        $db->insert('conversation_members', ['conversation_id' => $id, 'user_id' => $b, 'created_at' => now_utc()]);
        return $id;
    }

    public static function conversations(int $userId): array
    {
        return self::db()->fetchAll(
            "SELECT c.id AS conversation_id, c.type, c.title, cm.unread_count, cm.is_pinned, cm.is_muted,
                    m.body AS last_body, m.type AS last_type, m.sender_id AS last_sender, m.created_at AS last_at,
                    (SELECT COUNT(*) FROM conversation_members x WHERE x.conversation_id = c.id) AS member_count,
                    u2.id AS peer_id, u2.username AS peer_username, u2.nickname AS peer_nickname, up2.avatar AS peer_avatar
             FROM conversation_members cm
             JOIN conversations c ON c.id = cm.conversation_id
             LEFT JOIN messages m ON m.id = c.last_message_id
             LEFT JOIN conversation_members cm2 ON cm2.conversation_id = c.id AND cm2.user_id != ?
             LEFT JOIN users u2 ON u2.id = cm2.user_id
             LEFT JOIN user_profiles up2 ON up2.user_id = u2.id
             WHERE cm.user_id = ?
             ORDER BY cm.is_pinned DESC, c.last_message_at DESC, c.id DESC",
            [$userId, $userId]
        );
    }

    public static function members(int $conversationId, int $exceptUserId = null): array
    {
        $sql = "SELECT cm.user_id, u.username, u.nickname, up.avatar
                FROM conversation_members cm JOIN users u ON u.id = cm.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                WHERE cm.conversation_id = ?" . ($exceptUserId ? ' AND cm.user_id != ?' : '');
        $params = $exceptUserId ? [$conversationId, $exceptUserId] : [$conversationId];
        return self::db()->fetchAll($sql, $params);
    }

    public static function messages(int $conversationId, int $userId, int $before = 0, int $limit = 40): array
    {
        return self::db()->fetchAll(
            "SELECT m.id, m.sender_id, m.type, m.body, m.reply_to_id, m.recalled_at, m.burn_mode, m.created_at,
                    u.username, u.nickname, up.avatar,
                    " . self::REPLY_SELECT . "
             FROM messages m
             JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_id = ?
             LEFT JOIN users u ON u.id = m.sender_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN messages rp ON rp.id = m.reply_to_id
             LEFT JOIN users ru ON ru.id = rp.sender_id
             WHERE m.conversation_id = ? AND m.destroyed_at IS NULL
               AND (? = 0 OR m.id < ?)
             ORDER BY m.id DESC
             LIMIT ?",
            [$userId, $conversationId, $before, $before, $limit]
        );
    }

    /** 以某条消息为锚点加载上下文（搜索结果跳转用）。 */
    public static function messagesAround(int $conversationId, int $userId, int $anchorId, int $limit = 30): array
    {
        if (!self::isMember($conversationId, $userId)) {
            return [];
        }
        $rows = self::db()->fetchAll(
            "SELECT m.id, m.sender_id, m.type, m.body, m.reply_to_id, m.recalled_at, m.burn_mode, m.created_at,
                    u.username, u.nickname, up.avatar,
                    " . self::REPLY_SELECT . "
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN messages rp ON rp.id = m.reply_to_id
             LEFT JOIN users ru ON ru.id = rp.sender_id
             WHERE m.conversation_id = ? AND m.destroyed_at IS NULL
               AND m.id >= ? AND m.id <= ?
             ORDER BY m.id ASC
             LIMIT ?",
            [$conversationId, max(1, $anchorId - 12), $anchorId + 12, $limit]
        );
        return $rows;
    }

    /** 会话内消息搜索（仅本人所属会话）。 */
    public static function searchMessages(int $conversationId, int $userId, string $keyword, int $limit = 30): array
    {
        if (!self::isMember($conversationId, $userId)) {
            return [];
        }
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        return self::db()->fetchAll(
            "SELECT m.id, m.sender_id, m.type, m.body, m.created_at, u.username, u.nickname
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ? AND m.destroyed_at IS NULL AND m.recalled_at IS NULL
               AND m.type = 'text' AND m.body LIKE ?
             ORDER BY m.id DESC
             LIMIT ?",
            [$conversationId, $like, $limit]
        );
    }

    public static function send(
        int $conversationId,
        int $senderId,
        string $type,
        string $body,
        ?int $replyTo = null,
        string $burnMode = 'none',
        int $burnValue = 0
    ): array {
        $db = self::db();
        if (!self::isMember($conversationId, $senderId)) {
            throw new \RuntimeException('not_member');
        }
        // 会话级免打扰由接收方设置，不阻止发送
        $expires = $burnMode === 'after_time' && $burnValue > 0 ? date('Y-m-d H:i:s', time() + $burnValue) : null;
        $id = $db->insert('messages', [
            'conversation_id' => $conversationId,
            'sender_id'       => $senderId,
            'type'            => $type,
            'body'            => $body,
            'reply_to_id'     => $replyTo,
            'burn_mode'       => $burnMode,
            'burn_value'      => $burnValue,
            'expires_at'      => $expires,
            'created_at'      => now_utc(),
        ]);

        if ($type === 'location') {
            $loc = json_decode($body, true) ?: [];
            if (isset($loc['lat'], $loc['lng'])) {
                $db->insert('message_locations', ['message_id' => $id, 'lat' => $loc['lat'], 'lng' => $loc['lng'], 'label' => $loc['label'] ?? '']);
            }
        } elseif ($type === 'image' || $type === 'file') {
            $meta = json_decode($body, true) ?: [];
            if (!empty($meta['path'])) {
                $db->insert('message_attachments', [
                    'message_id' => $id,
                    'file_path'  => (string) $meta['path'],
                    'file_name'  => mb_substr((string) ($meta['name'] ?? ''), 0, 255),
                    'mime'       => mb_substr((string) ($meta['mime'] ?? ''), 0, 128),
                    'size'       => (int) ($meta['size'] ?? 0),
                    'width'      => isset($meta['w']) ? (int) $meta['w'] : null,
                    'height'     => isset($meta['h']) ? (int) $meta['h'] : null,
                    'thumb_path' => $meta['thumb'] ?? null,
                    'created_at' => now_utc(),
                ]);
            }
        }

        $db->update('conversations', ['last_message_id' => $id, 'last_message_at' => now_utc()], 'id = ?', [$conversationId]);
        // 其它成员未读 +1
        $db->statement(
            'UPDATE conversation_members SET unread_count = unread_count + 1 WHERE conversation_id = ? AND user_id != ?',
            [$conversationId, $senderId]
        );
        // 通知其它成员（会话免打扰 / 全局免打扰在 NotificationService 内静默）
        $others = $db->fetchAll(
            'SELECT user_id, is_muted FROM conversation_members WHERE conversation_id = ? AND user_id != ?',
            [$conversationId, $senderId]
        );
        foreach ($others as $o) {
            $peerId = (int) $o['user_id'];
            // 实时增量（免打扰只静默通知，仍推送未读）
            RealtimeService::push($peerId, 'message', [
                'conversation_id' => $conversationId,
                'message_id'      => $id,
                'sender_id'       => $senderId,
                'type'            => $type,
                'preview'         => $type === 'text' ? mb_substr($body, 0, 60) : $type,
            ]);
            if ((int) $o['is_muted'] === 1) {
                continue;
            }
            NotificationService::notify($peerId, 'message', $senderId, 'notification.message', $conversationId, 'conversation');
        }
        self::clearTyping($conversationId, $senderId);
        return self::getMessage($id, $senderId);
    }

    public static function getMessage(int $id, int $viewerId): array
    {
        return (array) self::db()->fetch(
            "SELECT m.id, m.sender_id, m.type, m.body, m.reply_to_id, m.recalled_at, m.burn_mode, m.created_at,
                    u.username, u.nickname, up.avatar,
                    " . self::REPLY_SELECT . "
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN messages rp ON rp.id = m.reply_to_id
             LEFT JOIN users ru ON ru.id = rp.sender_id
             WHERE m.id = ? LIMIT 1",
            [$id]
        );
    }

    public static function recall(int $messageId, int $userId): bool
    {
        $db = self::db();
        $msg = $db->fetch('SELECT id, sender_id, created_at FROM messages WHERE id = ?', [$messageId]);
        if (!$msg || (int) $msg['sender_id'] !== $userId) {
            return false;
        }
        if (time() - strtotime($msg['created_at']) > 120) {
            return false; // 超过 2 分钟撤回窗口
        }
        $db->update('messages', ['recalled_at' => now_utc(), 'type' => 'recall', 'body' => ''], 'id = ?', [$messageId]);
        return true;
    }

    public static function markRead(int $conversationId, int $userId): void
    {
        $db = self::db();
        $maxId = (int) $db->column(
            'SELECT MAX(id) FROM messages WHERE conversation_id = ? AND destroyed_at IS NULL',
            [$conversationId]
        );
        if ($maxId <= 0) {
            return;
        }
        $db->update('conversation_members', ['last_read_message_id' => $maxId, 'unread_count' => 0, 'last_read_at' => now_utc()], 'conversation_id = ? AND user_id = ?', [$conversationId, $userId]);
        $db->statement(
            "INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
             SELECT id, ?, ? FROM messages WHERE conversation_id = ? AND id <= ? AND sender_id != ?",
            [$userId, now_utc(), $conversationId, $maxId, $userId]
        );
        $db->statement(
            "UPDATE messages SET destroyed_at = ? WHERE conversation_id = ? AND burn_mode = 'after_view' AND sender_id != ? AND destroyed_at IS NULL AND id <= ?",
            [now_utc(), $conversationId, $userId, $maxId]
        );
    }

    /** 某条消息的已读人数（群会话用于「已读 N」）。 */
    public static function readCount(int $messageId): int
    {
        return (int) self::db()->column('SELECT COUNT(*) FROM message_reads WHERE message_id = ?', [$messageId]);
    }

    public static function toggleReaction(int $messageId, int $userId, string $emoji): array
    {
        $db = self::db();
        $exists = $db->fetch('SELECT id FROM message_reactions WHERE message_id = ? AND user_id = ? AND emoji = ? LIMIT 1', [$messageId, $userId, $emoji]);
        if ($exists) {
            $db->delete('message_reactions', 'message_id = ? AND user_id = ? AND emoji = ?', [$messageId, $userId, $emoji]);
            $added = false;
        } else {
            $db->insert('message_reactions', ['message_id' => $messageId, 'user_id' => $userId, 'emoji' => $emoji, 'created_at' => now_utc()]);
            $added = true;
        }
        $count = (int) $db->column('SELECT COUNT(*) FROM message_reactions WHERE message_id = ?', [$messageId]);
        return ['added' => $added, 'count' => $count, 'emoji' => $emoji];
    }

    public static function reactions(int $messageId): array
    {
        return self::db()->fetchAll(
            'SELECT emoji, COUNT(*) AS cnt FROM message_reactions WHERE message_id = ? GROUP BY emoji ORDER BY cnt DESC',
            [$messageId]
        );
    }

    public static function readReceipts(int $messageId): array
    {
        return self::db()->fetchAll(
            "SELECT mr.user_id, u.username, u.nickname, up.avatar
             FROM message_reads mr JOIN users u ON u.id = mr.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE mr.message_id = ? ORDER BY mr.read_at",
            [$messageId]
        );
    }

    public static function unreadTotals(int $userId): int
    {
        return (int) self::db()->column(
            'SELECT COALESCE(SUM(unread_count),0) FROM conversation_members WHERE user_id = ?',
            [$userId]
        );
    }

    // ------------------------------------------------------------------
    // 会话级操作
    // ------------------------------------------------------------------

    public static function isMember(int $conversationId, int $userId): bool
    {
        return self::db()->fetch(
            'SELECT id FROM conversation_members WHERE conversation_id = ? AND user_id = ? LIMIT 1',
            [$conversationId, $userId]
        ) !== null;
    }

    public static function setPinned(int $conversationId, int $userId, bool $on): bool
    {
        if (!self::isMember($conversationId, $userId)) {
            return false;
        }
        self::db()->update('conversation_members', ['is_pinned' => $on ? 1 : 0], 'conversation_id = ? AND user_id = ?', [$conversationId, $userId]);
        return true;
    }

    public static function setMuted(int $conversationId, int $userId, bool $on): bool
    {
        if (!self::isMember($conversationId, $userId)) {
            return false;
        }
        self::db()->update('conversation_members', ['is_muted' => $on ? 1 : 0], 'conversation_id = ? AND user_id = ?', [$conversationId, $userId]);
        return true;
    }

    /** 删除会话（仅移除自己的成员关系；无成员时清理会话本体）。 */
    public static function deleteConversation(int $conversationId, int $userId): bool
    {
        if (!self::isMember($conversationId, $userId)) {
            return false;
        }
        $db = self::db();
        $db->delete('conversation_members', 'conversation_id = ? AND user_id = ?', [$conversationId, $userId]);
        $remaining = (int) $db->column('SELECT COUNT(*) FROM conversation_members WHERE conversation_id = ?', [$conversationId]);
        if ($remaining === 0) {
            $db->delete('conversations', 'id = ?', [$conversationId]);
        }
        return true;
    }

    // ------------------------------------------------------------------
    // 正在输入（文件缓存，6 秒过期；轮询消息接口时一并返回）
    // ------------------------------------------------------------------

    public static function setTyping(int $conversationId, int $userId): void
    {
        if (!self::isMember($conversationId, $userId)) {
            return;
        }
        Cache::set('typing:' . $conversationId . ':' . $userId, time(), 6);
    }

    /** 除自己外正在输入的人（昵称列表）。 */
    public static function typingNames(int $conversationId, int $userId): array
    {
        $members = self::db()->fetchAll(
            'SELECT user_id FROM conversation_members WHERE conversation_id = ? AND user_id != ?',
            [$conversationId, $userId]
        );
        $names = [];
        foreach ($members as $m) {
            $uid = (int) $m['user_id'];
            if (Cache::get('typing:' . $conversationId . ':' . $uid, null) === null) {
                continue;
            }
            $row = self::db()->fetch('SELECT username, nickname FROM users WHERE id = ? LIMIT 1', [$uid]);
            if ($row) {
                $names[] = $row['nickname'] !== '' && $row['nickname'] !== null ? $row['nickname'] : $row['username'];
            }
        }
        return $names;
    }

    private static function clearTyping(int $conversationId, int $userId): void
    {
        Cache::delete('typing:' . $conversationId . ':' . $userId);
    }
}
