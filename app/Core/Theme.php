<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 当前主题与外观模式解析结果（由 ThemeMiddleware 设置），
 * 供布局输出 data 属性，前端据此加载对应主题 CSS。
 */
class Theme
{
    private static array $current = ['theme' => 'mono', 'mode' => 'dark'];

    public static function set(string $theme, string $mode): void
    {
        self::$current = ['theme' => $theme, 'mode' => $mode];
    }

    public static function current(): array
    {
        return self::$current;
    }

    public static function attributes(): string
    {
        return 'data-apx-theme="' . e(self::$current['theme']) . '" data-apx-mode="' . e(self::$current['mode']) . '"';
    }
}
