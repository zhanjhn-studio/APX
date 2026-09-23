#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * APX v2 - 异步队列 worker 常驻进程。
 * 阻塞消费 Redis 队列，按 job 名分发（mail / thumbnail）。
 * Redis 不可用时自动退出（由 systemd/supervisor 重启）。
 */
$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
spl_autoload_register(function (string $class) use ($root): void {
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

use App\Services\QueueService;

$timeout = isset($argv[1]) ? (int) $argv[1] : 5;
QueueService::worker($timeout);
