<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Config;
use App\Core\JsonResponse;
use App\Core\Redis;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\RealtimeService;
use App\Services\RealtimeTicketService;

/**
 * 实时事件通道（SSE + 轮询回退）。事件源统一来自 RealtimeService（RealtimeInterface 实现），
 * 两条通道各自 drain 队列即可送达；后续换 WebSocket 只需替换该实现。
 */
class EventsController
{
    /** SSE 长连接：持续下发队列事件，闲置时发送心跳保活。 */
    public function stream(Request $req): void
    {
        $uid = (int) AuthService::userId();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        @set_time_limit(0);

        $this->send('connected', ['t' => time(), 'gateway' => 'sse']);
        $lastBeat = time();
        $deadline = time() + 300; // 单连接最长 5 分钟，前端会自动重连
        while (true) {
            if (connection_status() !== 0) {
                break;
            }
            $events = RealtimeService::drain($uid);
            foreach ($events as $ev) {
                $this->send((string) ($ev['event'] ?? 'message'), (array) ($ev['payload'] ?? []));
            }
            if (time() - $lastBeat >= 25) {
                $this->send('ping', ['t' => time()]);
                $lastBeat = time();
            }
            if (time() >= $deadline) {
                $this->send('bye', ['t' => time()]);
                break;
            }
            echo ": keep-alive\n\n";
            @ob_flush();
            @flush();
            sleep(3);
        }
        exit;
    }

    /** 签发 WebSocket 连接票据（仅当 realtime_driver=ws 且 Redis 可用时返回非空 token）。 */
    public function ticket(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $token = '';
        if ((string) Config::get('app.realtime_driver', 'sse') === 'ws' && Redis::instance()->available()) {
            $token = RealtimeTicketService::issue($uid);
        }
        JsonResponse::ok([
            'token' => $token,
            'ws'    => Config::get('websocket.url', ''),
        ], 'ok');
    }

    /** 轮询回退：返回增量事件与未读计数，供前端更新徽标与提示。 */
    public function poll(Request $req): void
    {
        $uid = (int) AuthService::userId();
        JsonResponse::ok([
            'events' => RealtimeService::drain($uid),
            'counts' => [
                'notifications' => NotificationService::unreadCount($uid),
                'messages'      => MessageService::unreadTotals($uid),
            ],
        ], 'ok');
    }

    private function send(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode(['event' => $event, 'payload' => $payload], JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        @flush();
    }
}
