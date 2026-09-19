<?php
declare(strict_types=1);
namespace App\Interfaces;

/**
 * 通知投递契约。实现方需负责「免打扰静默」「不通知自己」等业务规则。
 */
interface NotifierInterface
{
    public static function notify(int $userId, string $type, int $actorId, string $body, ?int $relatedId = null, ?string $relatedType = null): void;

    public static function unreadCount(int $userId): int;

    public static function markAllRead(int $userId): void;
}
