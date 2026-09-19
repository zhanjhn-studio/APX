<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 国际化。语言包为扁平数组，key 采用 域.模块.键。
 * 翻译缺失时回退到默认语言包，再缺失则返回 key 以便发现遗漏。
 */
class I18n
{
    private static string $locale = 'zh-CN';
    private static array $packs = [];

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, ['zh-CN', 'zh-TW', 'en'], true)) {
            self::$locale = $locale;
        }
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function available(): array
    {
        return ['zh-CN', 'zh-TW', 'en'];
    }

    private static function load(string $locale): array
    {
        if (isset(self::$packs[$locale])) {
            return self::$packs[$locale];
        }
        $file = APP_ROOT . '/lang/' . $locale . '.php';
        self::$packs[$locale] = is_file($file) ? (array) require $file : [];
        return self::$packs[$locale];
    }

    public static function translate(string $key, array $params = []): string
    {
        $val = self::resolve($key);
        if ($val === null) {
            return $key;
        }
        return self::replace($val, $params);
    }

    private static function resolve(string $key): ?string
    {
        $pack = self::load(self::$locale);
        if (isset($pack[$key])) {
            return $pack[$key];
        }
        $def = Config::get('app.lang_default', 'zh-CN');
        if ($def !== self::$locale) {
            $d = self::load($def);
            if (isset($d[$key])) {
                return $d[$key];
            }
        }
        return null;
    }

    public static function choice(string $key, int $count, array $params = []): string
    {
        $suffix = $count === 1 ? '_one' : '_other';
        $val = self::resolve($key . $suffix);
        if ($val === null) {
            $val = self::resolve($key);
        }
        if ($val === null) {
            return $key;
        }
        return self::replace($val, array_merge($params, [':count' => $count]));
    }

    private static function replace(string $val, array $params): string
    {
        foreach ($params as $k => $v) {
            $token = strpos((string) $k, ':') === 0 ? (string) $k : ':' . $k;
            $val = str_replace($token, (string) $v, $val);
        }
        return $val;
    }

    /** 导出当前语言包给前端 JS 使用 */
    public static function export(): array
    {
        return self::load(self::$locale);
    }
}
