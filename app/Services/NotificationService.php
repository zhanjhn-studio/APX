<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Interfaces\NotifierInterface;

/**
 * 统一通知中心。覆盖好友申请、点赞、评论、回复、私聊、群组邀请、系统通知。
 * 免打扰时段内自动静默社交类通知；写入成功后通过 RealtimeService 推送增量事件。
 */
class NotificationService implements NotifierInterface
{
    /** 免打扰时段内静默的社交类通知（好友申请 / 群组 / 系统通知不受影响）。 */
    private const DND_MUTED_TYPES = ['message', 'like', 'comment', 'reply', 'mention', 'repost'];

    public static function notify(int $userId, string $type, int $actorId, string $body, ?int $relatedId = null, ?string $relatedType = null): void
    {
        if ($userId === $actorId) {
            return; // 不通知自己
        }
        if (in_array($type, self::DND_MUTED_TYPES, true) && SettingsService::isDndActive($userId)) {
            return; // 夜间 / 自定义免打扰时段内不打扰
        }
        Database::instance()->insert('notifications', [
            'user_id' => $userId,
            'actor_id' => $actorId,
            'type' => $type,
            'body' => $body,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'created_at' => now_utc(),
        ]);
        Realtime::push($userId, 'notification', [
            'type'         => $type,
            'actor_id'     => $actorId,
            'body'         => $body,
            'related_id'   => $relatedId,
            'related_type' => $relatedType,
            'unread'       => self::unreadCount($userId),
        ]);
    }

    /**
     * 系统通知（无触发者，如新设备登录提醒）。
     * $extra 通过 body 的「key|附加文本」约定传给视图，映射为 :device / :name 占位符。
     */
    public static function notifySystem(int $userId, string $body, string $extra = '', ?int $relatedId = null, ?string $relatedType = null): void
    {
        if ($userId <= 0) {
            return;
        }
        $payload = $body . ($extra !== '' ? '|' . $extra : '');
        Database::instance()->insert('notifications', [
            'user_id' => $userId,
            'actor_id' => null,
            'type' => 'system',
            'body' => $payload,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'created_at' => now_utc(),
        ]);
        Realtime::push($userId, 'notification', [
            'type'         => 'system',
            'actor_id'     => null,
            'body'         => $payload,
            'related_id'   => $relatedId,
            'related_type' => $relatedType,
            'unread'       => self::unreadCount($userId),
        ]);
    }

    public static function list(int $userId, int $limit = 30): array
    {
        return Database::instance()->fetchAll(
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
        return (int) Database::instance()->column(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::instance()->statement(
            'UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL',
            [now_utc(), $userId]
        );
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::instance()->statement(
            'UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [now_utc(), $id, $userId]
        );
    }
}
