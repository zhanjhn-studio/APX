<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 文件缓存。存储目录 storage/cache，禁止 Web 直接访问（见 nginx 配置）。
 */
class Cache
{
    private static function dir(): string
    {
        $dir = rtrim(Config::get('app.cache_dir'), '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . md5($key) . '.cache';
    }

    public static function get(string $key, $default = null)
    {
        $f = self::path($key);
        if (!is_file($f)) {
            return $default;
        }
        $content = file_get_contents($f);
        $exp = (int) substr($content, 0, 12);
        if ($exp !== 0 && $exp < time()) {
            @unlink($f);
            return $default;
        }
        $data = unserialize(substr($content, 12));
        return $data === false ? $default : $data;
    }

    public static function set(string $key, $value, int $ttl = 0): void
    {
        $f = self::path($key);
        $exp = $ttl > 0 ? time() + $ttl : 0;
        file_put_contents($f, str_pad((string) $exp, 12, '0', STR_PAD_LEFT) . serialize($value), LOCK_EX);
    }

    public static function delete(string $key): void
    {
        @unlink(self::path($key));
    }

    public static function remember(string $key, int $ttl, callable $cb)
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $cb();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function clear(): int
    {
        $dir = self::dir();
        $count = 0;
        foreach (glob($dir . '/*.cache') ?: [] as $f) {
            if (@unlink($f)) {
                $count++;
            }
        }
        return $count;
    }
}
