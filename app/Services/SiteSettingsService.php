<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 站点级配置（settings 表，非用户级）。全站共享，按请求缓存一次。
 * 与 user_settings 明确区分：本服务永不按用户隔离。
 */
class SiteSettingsService
{
    private static ?array $cache = null;

    /** 允许后台写入的键白名单（防止任意键注入）。 */
    public const WRITABLE = [
        'site_name', 'site_slogan', 'register_mode', 'allow_registration',
        'default_theme', 'default_mode', 'default_language', 'maintenance',
        'smtp_dev_mode', 'waf_mode', 'announcement',
        'custom_html_enabled', 'custom_html_position', 'custom_html_content',
        'custom_html_instruction', 'custom_html_interface',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::instance()->fetchAll('SELECT `key`, `value` FROM settings') as $row) {
                    self::$cache[(string) $row['key']] = $row['value'];
                }
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        return array_key_exists($key, $all) ? (string) $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, null);
        if ($v === null) {
            return $default;
        }
        return $v === '1' || strtolower($v) === 'true';
    }

    public static function set(string $key, string $value, string $group = 'general'): bool
    {
        if (!in_array($key, self::WRITABLE, true)) {
            return false;
        }
        Database::instance()->statement(
            'INSERT INTO settings (`key`, `value`, `group`, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`), updated_at = VALUES(updated_at)',
            [$key, $value, $group, now_utc(), now_utc()]
        );
        self::$cache = null;
        return true;
    }

    /** 批量写入（仅白名单键）。 */
    public static function setMany(array $pairs, string $group = 'general'): int
    {
        $n = 0;
        foreach ($pairs as $k => $v) {
            if (self::set((string) $k, (string) $v, $group)) {
                $n++;
            }
        }
        return $n;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    public const GROUP_MAP = [
        'site_name' => 'general',
        'site_slogan' => 'general',
        'maintenance' => 'general',
        'announcement' => 'general',
        'register_mode' => 'auth',
        'allow_registration' => 'auth',
        'default_theme' => 'appearance',
        'default_mode' => 'appearance',
        'default_language' => 'i18n',
        'smtp_dev_mode' => 'mail',
        'waf_mode' => 'security',
        'custom_html_enabled' => 'custom_page',
        'custom_html_position' => 'custom_page',
        'custom_html_content' => 'custom_page',
        'custom_html_instruction' => 'custom_page',
        'custom_html_interface' => 'custom_page',
    ];
}
