<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Theme;

/**
 * 主题与外观模式解析：Cookie → 用户设置 → 站点默认。
 */
class ThemeMiddleware
{
    public static function handle(Request $req): bool
    {
        if (defined('APX_INSTALLING')) {
            return true;
        }
        $theme = $_COOKIE['apx_theme'] ?? null;
        $mode = $_COOKIE['apx_mode'] ?? null;

        if (Session::userId()) {
            $rows = Database::instance()->fetchAll(
                'SELECT `key`, value FROM user_settings WHERE user_id = ? AND `key` IN ("theme","appearance")',
                [Session::userId()]
            );
            $set = [];
            foreach ($rows as $r) {
                $set[$r['key']] = $r['value'];
            }
            if (!$theme && isset($set['theme'])) {
                $theme = $set['theme'];
            }
            if (!$mode && isset($set['appearance'])) {
                $mode = $set['appearance'];
            }
        }

        $theme = $theme ?: Config::get('app.theme_default', 'graphite');
        $mode = $mode ?: Config::get('app.mode_default', 'dark');
        Theme::set($theme, $mode);
        return true;
    }
}
