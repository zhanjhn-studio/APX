<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Session;

/**
 * 语言解析：用户设置 → Cookie → Accept-Language → 站点默认。
 */
class LocaleMiddleware
{
    public static function handle(Request $req): bool
    {
        if (defined('APX_INSTALLING')) {
            return true;
        }
        $locale = null;
        if (Session::userId()) {
            $row = Database::instance()->fetch(
                'SELECT value FROM user_settings WHERE user_id = ? AND `key` = ? LIMIT 1',
                [Session::userId(), 'language']
            );
            if ($row) {
                $locale = $row['value'];
            }
        }
        if (!$locale && !empty($_COOKIE['apx_locale'])) {
            $locale = $_COOKIE['apx_locale'];
        }
        if (!$locale) {
            $locale = self::fromAccept();
        }
        if (!$locale) {
            $locale = Config::get('app.lang_default', 'zh-CN');
        }
        I18n::setLocale($locale);
        return true;
    }

    private static function fromAccept(): ?string
    {
        $h = trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($h === '') {
            return null;
        }
        $parsed = [];
        foreach (preg_split('/,\s*/', $h) ?: [] as $part) {
            if (!preg_match('/^([a-zA-Z]{1,3})(?:-([a-zA-Z0-9]{1,8}))?/i', $part, $m)) {
                continue;
            }
            $lang = strtolower($m[1]);
            $region = isset($m[2]) ? strtolower($m[2]) : '';
            $q = 1.0;
            if (preg_match('/;\s*q\s*=\s*([0-9.]+)/i', $part, $qm)) {
                $q = (float) $qm[1];
            }
            $parsed[] = ['lang' => $lang, 'region' => $region, 'q' => $q];
        }
        if ($parsed === []) {
            return null;
        }
        // 按质量 q 降序，取第一个受支持的语言（不再做子串匹配，避免 zh-CN,en 被误判为英文）
        usort($parsed, fn($a, $b) => $b['q'] <=> $a['q']);
        foreach ($parsed as $p) {
            $locale = self::mapLocale($p['lang'], $p['region']);
            if ($locale !== null) {
                return $locale;
            }
        }
        return null;
    }

    private static function mapLocale(string $lang, string $region): ?string
    {
        if ($lang === 'zh') {
            return ($region === 'tw' || $region === 'hant') ? 'zh-TW' : 'zh-CN';
        }
        if ($lang === 'en') {
            return 'en';
        }
        return null;
    }
}
