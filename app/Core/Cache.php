<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 缓存。驱动可切换：redis（优先，需 Predis + Redis 服务）或 file（默认，零依赖）。
 * Redis 不可用时自动回退 file，绝不因缺 Redis 导致整站 500。
 */
class Cache
{
    private static ?string $driver = null;

    private static function driver(): string
    {
        if (self::$driver === null) {
            $cfg = (string) Config::get('app.cache_driver', 'file');
            if ($cfg === 'redis' && Redis::instance()->available()) {
                self::$driver = 'redis';
            } else {
                self::$driver = 'file';
            }
        }
        return self::$driver;
    }

    public static function get(string $key, $default = null)
    {
        return self::driver() === 'redis'
            ? self::redisGet($key, $default)
            : self::fileGet($key, $default);
    }

    public static function set(string $key, $value, int $ttl = 0): void
    {
        if (self::driver() === 'redis') {
            self::redisSet($key, $value, $ttl);
        } else {
            self::fileSet($key, $value, $ttl);
        }
    }

    public static function delete(string $key): void
    {
        if (self::driver() === 'redis') {
            self::redisDelete($key);
        } else {
            self::fileDelete($key);
        }
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
        return self::driver() === 'redis' ? self::redisClear() : self::fileClear();
    }

    // ---------------- file driver（原有实现） ----------------

    private static function fileDir(): string
    {
        $dir = rtrim((string) Config::get('app.cache_dir'), '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function filePath(string $key): string
    {
        return self::fileDir() . '/' . md5($key) . '.cache';
    }

    private static function fileGet(string $key, $default)
    {
        $f = self::filePath($key);
        if (!is_file($f)) {
            return $default;
        }
        $content = file_get_contents($f);
        if ($content === false) {
            return $default;
        }
        $exp = (int) substr($content, 0, 12);
        if ($exp !== 0 && $exp < time()) {
            @unlink($f);
            return $default;
        }
        $data = unserialize(substr($content, 12));
        return $data === false ? $default : $data;
    }

    private static function fileSet(string $key, $value, int $ttl): void
    {
        $f = self::filePath($key);
        $exp = $ttl > 0 ? time() + $ttl : 0;
        file_put_contents($f, str_pad((string) $exp, 12, '0', STR_PAD_LEFT) . serialize($value), LOCK_EX);
    }

    private static function fileDelete(string $key): void
    {
        @unlink(self::filePath($key));
    }

    private static function fileClear(): int
    {
        $dir = self::fileDir();
        $count = 0;
        foreach (glob($dir . '/*.cache') ?: [] as $f) {
            if (@unlink($f)) {
                $count++;
            }
        }
        return $count;
    }

    // ---------------- redis driver ----------------

    private static function rk(string $key): string
    {
        return 'apx:cache:' . md5($key);
    }

    private static function redisGet(string $key, $default)
    {
        $c = Redis::instance()->client();
        if ($c === null) {
            return $default;
        }
        try {
            $raw = $c->get(self::rk($key));
            if ($raw === null) {
                return $default;
            }
            $data = unserialize($raw);
            return $data === false ? $default : $data;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function redisSet(string $key, $value, int $ttl): void
    {
        $c = Redis::instance()->client();
        if ($c === null) {
            return;
        }
        try {
            $k = self::rk($key);
            $val = serialize($value);
            if ($ttl > 0) {
                $c->setex($k, $ttl, $val);
            } else {
                $c->set($k, $val);
            }
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    private static function redisDelete(string $key): void
    {
        $c = Redis::instance()->client();
        if ($c === null) {
            return;
        }
        try {
            $c->del([self::rk($key)]);
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    private static function redisClear(): int
    {
        $c = Redis::instance()->client();
        if ($c === null) {
            return 0;
        }
        try {
            $count = 0;
            $it = 0;
            do {
                $keys = $c->scan($it, 'MATCH', 'apx:cache:*', 'COUNT', 200);
                if (!empty($keys)) {
                    $c->del($keys);
                    $count += count($keys);
                }
            } while ($it > 0);
            return $count;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
