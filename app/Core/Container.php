<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 极简服务容器。用于缓存单例服务（PDO、Mail、Upload 等）。
 */
class Container
{
    private static array $instances = [];
    private static array $bindings = [];

    public static function bind(string $key, callable $factory): void
    {
        self::$bindings[$key] = $factory;
    }

    public static function singleton(string $key, callable $factory): void
    {
        self::$bindings[$key] = static function () use ($key, $factory) {
            if (!isset(self::$instances[$key])) {
                self::$instances[$key] = $factory();
            }
            return self::$instances[$key];
        };
    }

    public static function get(string $key)
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }
        if (isset(self::$bindings[$key])) {
            return (self::$bindings[$key])();
        }
        throw new \RuntimeException('未绑定的服务：' . $key);
    }

    public static function has(string $key): bool
    {
        return isset(self::$instances[$key]) || isset(self::$bindings[$key]);
    }

    public static function flush(): void
    {
        self::$instances = [];
    }
}
