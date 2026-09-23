<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Redis;
use App\Interfaces\RealtimeInterface;

/**
 * 实时推送（v2：Redis + WebSocket 实现）。
 * - push：向 rt:{userId} 频道 publish（WS 进程订阅后即时转发），同时写入 rt:buf:{userId} 缓冲队列
 *   （TTL 内），供用户（重）连时补发未达事件。
 * - drain：取出并清空缓冲队列（WS 连接建立时调用）。
 * - online：依据 WS 进程维护的 rt:online:{userId} 集合判断真实连接态。
 * Redis 不可用时本实现不可用，调用方应回退到 RealtimeService（文件队列 + SSE）。
 */
class RedisRealtimeService implements RealtimeInterface
{
    private const BUF_TTL = 600;
    private const MAX_QUEUE = 50;

    public static function push(int $userId, string $event, array $payload): void
    {
        if ($userId <= 0 || $event === '') {
            return;
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return;
        }
        $item = json_encode(
            ['event' => $event, 'payload' => $payload, 'at' => time()],
            JSON_UNESCAPED_UNICODE
        );
        try {
            $buf = self::bufKey($userId);
            $c->rpush($buf, [$item]);
            $c->expire($buf, self::BUF_TTL);
            $c->ltrim($buf, -self::MAX_QUEUE, -1);
            $c->publish(self::chan($userId), $item);
        } catch (\Throwable $e) {
            // 推送失败不影响业务主流程
        }
    }

    public static function drain(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return [];
        }
        try {
            $buf = self::bufKey($userId);
            $raw = $c->lrange($buf, 0, -1);
            $c->del([$buf]);
            $out = [];
            foreach ($raw as $line) {
                $dec = json_decode($line, true);
                if (is_array($dec)) {
                    $out[] = $dec;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function online(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return false;
        }
        try {
            return (int) $c->scard(self::onlineKey($userId)) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** WS 进程在连接建立时调用，标记用户在线。 */
    public static function markOnline(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return;
        }
        try {
            $c->sadd(self::onlineKey($userId), [(string) $userId]);
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    /** WS 进程在连接断开时调用，移除在线标记。 */
    public static function markOffline(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return;
        }
        try {
            $c->srem(self::onlineKey($userId), [(string) $userId]);
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    private static function chan(int $userId): string
    {
        return 'rt:' . $userId;
    }

    private static function bufKey(int $userId): string
    {
        return 'rt:buf:' . $userId;
    }

    private static function onlineKey(int $userId): string
    {
        return 'rt:online:' . $userId;
    }
}
