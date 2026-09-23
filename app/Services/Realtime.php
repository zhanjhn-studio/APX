<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use App\Core\Redis;
use App\Interfaces\RealtimeInterface;

/**
 * 实时推送门面：按 config app.realtime_driver 选择实现。
 * - ws：RedisRealtimeService（WebSocket + Redis）
 * - sse：RealtimeService（文件队列回退）
 * Redis 不可用时自动回退 sse。后续接入 WebSocket 时，把调用方从
 * RealtimeService:: 改为 Realtime:: 即可零改动切换驱动。
 */
class Realtime
{
    private static ?RealtimeInterface $impl = null;

    public static function impl(): RealtimeInterface
    {
        if (self::$impl === null) {
            $driver = (string) Config::get('app.realtime_driver', 'sse');
            if ($driver === 'ws'
                && class_exists(RedisRealtimeService::class)
                && Redis::instance()->available()) {
                self::$impl = new RedisRealtimeService();
            } else {
                self::$impl = new RealtimeService();
            }
        }
        return self::$impl;
    }

    public static function push(int $userId, string $event, array $payload): void
    {
        self::impl()->push($userId, $event, $payload);
    }

    public static function drain(int $userId): array
    {
        return self::impl()->drain($userId);
    }

    public static function online(int $userId): bool
    {
        return self::impl()->online($userId);
    }
}
