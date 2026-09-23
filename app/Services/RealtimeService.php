<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Cache;
use App\Interfaces\RealtimeInterface;

/**
 * 实时事件队列（SSE 文件队列回退实现）。
 * 每个用户一个定长队列（保留最近 50 条，10 分钟过期），SSE 与轮询各自 drain 一次即可送达。
 * v2 起作为 realtime_driver=sse 的回退实现；WebSocket 场景由 RedisRealtimeService 承担。
 */
class RealtimeService implements RealtimeInterface
{
    private const MAX_QUEUE = 50;
    private const TTL = 600;

    public static function push(int $userId, string $event, array $payload): void
    {
        if ($userId <= 0 || $event === '') {
            return;
        }
        try {
            $key = self::key($userId);
            $list = Cache::get($key, []);
            if (!is_array($list)) {
                $list = [];
            }
            $list[] = ['event' => $event, 'payload' => $payload, 'at' => time()];
            if (count($list) > self::MAX_QUEUE) {
                $list = array_slice($list, -self::MAX_QUEUE);
            }
            Cache::set($key, $list, self::TTL);
        } catch (\Throwable $e) {
            // 推送失败不影响业务主流程
        }
    }

    public static function drain(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $key = self::key($userId);
            $list = Cache::get($key, []);
            Cache::delete($key);
            return is_array($list) ? $list : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function key(int $userId): string
    {
        return 'rt_queue:' . $userId;
    }

    /** 文件队列实现无法判断真实连接态，统一返回 false。 */
    public static function online(int $userId): bool
    {
        return false;
    }
}
