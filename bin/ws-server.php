#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * APX v2 - WebSocket 实时服务进程。
 * 依赖 Redis（Predis）与 Ratchet；通过 redis 缓冲队列（rt:buf:{userId}）下发事件。
 * 由 systemd / supervisor 托管。Nginx 可将 /ws 反代到本进程端口（默认 8080）。
 *
 * 设计：每个在线用户连接建立时凭票据换取 userId 并标记在线；服务端以 0.3s 周期
 * 从 Redis 缓冲队列 pump 事件推送给对应连接（替代 pub/sub，单进程即可，简单可靠）。
 * Redis 不可用时本进程无法工作，前端会自动回退 SSE。
 */

// ---- 最小引导：仅装载自研类 + Redis，不做 Web 重定向 ----
$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = $root . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require_once $root . '/app/Helpers/functions.php';

use App\Core\Config;
use App\Services\RealtimeTicketService;
use App\Services\RedisRealtimeService;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class ApxWs implements MessageComponentInterface
{
    /** @var array<int, array{conn: ConnectionInterface, uid: int}> */
    private array $clients = [];
    /** @var array<int, int> resourceId => userId */
    private array $uidByConn = [];

    public function onOpen(ConnectionInterface $conn): void
    {
        $uid = 0;
        try {
            $query = $conn->httpRequest->getUri()->getQuery();
            parse_str($query, $params);
            $token = (string) ($params['token'] ?? '');
            $uid = RealtimeTicketService::consume($token);
        } catch (\Throwable $e) {
            $uid = 0;
        }
        if ($uid <= 0) {
            $conn->close();
            return;
        }
        $rid = $conn->resourceId;
        $this->clients[$rid] = ['conn' => $conn, 'uid' => $uid];
        $this->uidByConn[$rid] = $uid;
        RedisRealtimeService::markOnline($uid);
        // 连接建立即补发缓冲事件，避免票据签发前的遗漏
        foreach (RedisRealtimeService::drain($uid) as $ev) {
            $this->send($conn, $ev);
        }
        $this->send($conn, ['type' => 'connected', 'gateway' => 'ws', 't' => time()]);
    }

    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        try {
            $data = json_decode((string) $msg, true);
            if (is_array($data) && ($data['type'] ?? '') === 'ping') {
                $this->send($conn, ['type' => 'pong', 't' => time()]);
            }
        } catch (\Throwable $e) {
            // 忽略非预期消息
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $rid = $conn->resourceId;
        $uid = $this->uidByConn[$rid] ?? 0;
        unset($this->clients[$rid], $this->uidByConn[$rid]);
        $stillOnline = false;
        foreach ($this->uidByConn as $u) {
            if ($u === $uid) {
                $stillOnline = true;
                break;
            }
        }
        if ($uid > 0 && !$stillOnline) {
            RedisRealtimeService::markOffline($uid);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        try {
            $conn->close();
        } catch (\Throwable $t) {
            // 忽略
        }
    }

    /** 定时补发各在线用户的缓冲事件（替代 pub/sub）。 */
    public function pump(): void
    {
        if (empty($this->uidByConn)) {
            return;
        }
        $seen = [];
        foreach ($this->uidByConn as $rid => $uid) {
            if (isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $events = RedisRealtimeService::drain($uid);
            if (empty($events)) {
                continue;
            }
            foreach ($this->clients as $entry) {
                if ($entry['uid'] === $uid) {
                    foreach ($events as $ev) {
                        $this->send($entry['conn'], $ev);
                    }
                }
            }
        }
    }

    /** 心跳：每 30s 发一次，供前端判断连接存活。 */
    public function heartbeat(): void
    {
        foreach ($this->clients as $entry) {
            $this->send($entry['conn'], ['type' => 'heartbeat', 't' => time()]);
        }
    }

    private function send(ConnectionInterface $conn, array $payload): void
    {
        try {
            $conn->send(json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // 连接已断开，下次 onClose 清理
        }
    }
}

$port = (int) Config::get('websocket.port', 8080);
$host = (string) Config::get('websocket.host', '0.0.0.0');

if (!class_exists(\Ratchet\Server\IoServer::class)) {
    fwrite(STDERR, "Ratchet 未安装（需先 composer install）。WebSocket 服务无法启动；前端会回退 SSE。\n");
    exit(1);
}

$app = new ApxWs();
$ws = new WsServer($app);
$http = new HttpServer($ws);

// 兼容 React EventLoop v2 / v3
if (class_exists('React\\EventLoop\\Loop')) {
    $loop = \React\EventLoop\Loop::get();
} elseif (class_exists('React\\EventLoop\\Factory')) {
    $loop = (new \React\EventLoop\Factory())->create();
} else {
    fwrite(STDERR, "React EventLoop 不可用。\n");
    exit(1);
}

$loop->addPeriodicTimer(0.3, [$app, 'pump']);
$loop->addPeriodicTimer(30, [$app, 'heartbeat']);

$server = IoServer::factory($http, $port, $host, $loop);
fwrite(STDOUT, "APX WebSocket server listening on {$host}:{$port}\n");
$server->run();
