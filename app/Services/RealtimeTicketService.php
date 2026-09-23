<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Redis;

/**
 * 实时通道鉴权票据。WebSocket 是独立进程，无法读取 PHP 会话 Cookie，
 * 因此由 Web 侧在用户已登录时签发一次性短期票据（存 Redis），WS 进程凭票据换取 userId。
 * Redis 不可用时 issue() 返回空串，前端据此回退 SSE。
 */
class RealtimeTicketService
{
    private const TTL = 60;
    private const PREFIX = 'rt:ticket:';

    /** 为用户签发一张票据（60s 有效，单次消费）。 */
    public static function issue(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return '';
        }
        try {
            $token = bin2hex(random_bytes(24));
            $c->setex(self::PREFIX . $token, self::TTL, (string) $userId);
            return $token;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** 消费票据并换回 userId（消费后失效）。失败返回 0。 */
    public static function consume(string $token): int
    {
        $token = trim($token);
        if ($token === '') {
            return 0;
        }
        $c = Redis::instance()->client();
        if ($c === null) {
            return 0;
        }
        try {
            $key = self::PREFIX . $token;
            $uid = $c->get($key);
            if ($uid === null) {
                return 0;
            }
            $c->del([$key]);
            return (int) $uid;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
