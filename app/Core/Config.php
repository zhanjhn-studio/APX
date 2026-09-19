<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 配置读取。支持点分键 app.name，按文件缓存。
 * database 配置会合并 database.php（真实凭据，由安装向导生成）。
 */
class Config
{
    private static array $cache = [];

    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file = $parts[0];

        if (!array_key_exists($file, self::$cache)) {
            $data = [];
            $example = APP_ROOT . '/config/' . $file . '.php';
            if (is_file($example)) {
                $data = (array) require $example;
            }
            if ($file === 'database') {
                $real = APP_ROOT . '/config/database.php';
                if (is_file($real)) {
                    $data = array_merge($data, (array) require $real);
                }
            }
            self::$cache[$file] = $data;
        }

        $value = self::$cache[$file];
        for ($i = 1; $i < count($parts); $i++) {
            if (is_array($value) && array_key_exists($parts[$i], $value)) {
                $value = $value[$parts[$i]];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function all(string $file): array
    {
        self::get($file);
        return self::$cache[$file] ?? [];
    }

    public static function set(string $key, $value): void
    {
        $parts = explode('.', $key);
        $file = $parts[0];
        if (!array_key_exists($file, self::$cache)) {
            self::get($file);
        }
        $ref = &self::$cache[$file];
        for ($i = 1; $i < count($parts) - 1; $i++) {
            if (!isset($ref[$parts[$i]]) || !is_array($ref[$parts[$i]])) {
                $ref[$parts[$i]] = [];
            }
            $ref = &$ref[$parts[$i]];
        }
        $ref[$parts[count($parts) - 1]] = $value;
        unset($ref);
    }
}
