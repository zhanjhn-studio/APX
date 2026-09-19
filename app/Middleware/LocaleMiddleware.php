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
        $h = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (stripos($h, 'zh-tw') !== false || stripos($h, 'zh-hant') !== false) {
            return 'zh-TW';
        }
        if (stripos($h, 'en') !== false) {
            return 'en';
        }
        return 'zh-CN';
    }
}
