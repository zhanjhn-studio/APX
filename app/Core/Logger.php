<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 简易日志。写入 storage/logs，按天滚动。
 * 禁止记录密码、令牌、完整请求体。
 */
class Logger
{
    public static function write(string $channel, string $message, array $context = []): void
    {
        $dir = rtrim(Config::get('app.logs_dir'), '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = date('Y-m-d H:i:s') . ' [' . $channel . '] ' . $message
            . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE) : '')
            . PHP_EOL;
        @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $msg, array $ctx = []): void { self::write('info', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::write('error', $msg, $ctx); }
    public static function security(string $msg, array $ctx = []): void { self::write('security', $msg, $ctx); }
}
