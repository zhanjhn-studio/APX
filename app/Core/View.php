<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 视图渲染。页面模板位于 app/Views/pages，布局位于 app/Views/layouts。
 * 模板内可直接使用全局助手 e() / __() / route() / asset() 等。
 */
class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $viewFile = APP_ROOT . '/app/Views/pages/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('视图不存在：' . $view);
        }
        $content = self::capture($viewFile, $data);

        if ($layout === null) {
            return $content;
        }
        $layoutFile = APP_ROOT . '/app/Views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            return $content;
        }
        $data['content'] = $content;
        return self::capture($layoutFile, $data);
    }

    public static function partial(string $partial, array $data = []): string
    {
        $file = APP_ROOT . '/app/Views/partials/' . $partial . '.php';
        if (!is_file($file)) {
            return '';
        }
        return self::capture($file, $data);
    }

    /**
     * 输出若干 ES Module 脚本标签（页面级 JS）。
     * 接收 asset('js/...') 已生成的完整路径。
     */
    public static function scripts(array $assets): string
    {
        $out = '';
        foreach ($assets as $a) {
            $out .= '<script type="module" src="' . e((string) $a) . '"></script>' . "\n";
        }
        return $out;
    }

    private static function capture(string $file, array $data): string
    {
        ob_start();
        // 必须在「与 require 同一作用域」内 extract，闭包有独立作用域，
        // 否则视图/布局拿不到 $content、$title、$posts 等数据。
        (static function (string $__apxViewFile, array $__apxData): void {
            extract($__apxData, EXTR_SKIP);
            require $__apxViewFile;
        })($file, $data);
        return (string) ob_get_clean();
    }
}
