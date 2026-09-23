<?php
declare(strict_types=1);
namespace App\Interfaces;

/**
 * 实时推送契约。当前实现为「文件队列 + SSE/轮询」，
 * 后续接入 WebSocket / Redis 时只需替换实现，调用方（MessageService / NotificationService）无需改动。
 */
interface RealtimeInterface
{
    /** 向指定用户投递一个事件（不阻塞业务主流程）。 */
    public static function push(int $userId, string $event, array $payload): void;

    /** 取出并清空该用户的待推送事件（SSE 与轮询共用）。 */
    public static function drain(int $userId): array;

    /** 该用户是否处于真实连接态（WS 实现返回真实连接，回退实现返回 false）。 */
    public static function online(int $userId): bool;
}
