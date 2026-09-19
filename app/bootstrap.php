<?php
declare(strict_types=1);
/**
 * APX - 引导文件
 * 定义根路径、注册自动加载、载入助手函数、注册错误处理器。
 */
define('APP_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

// 静态资源直通：所有 /assets/*（兼容 /public/assets/*）统一从 public/assets/ 流式返回。
// 这样无论 web 根指向 public/ 还是项目根、是否配置服务器别名/rewrite，CSS/JS/图片都不会 404。
$__apxUri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (strpos($__apxUri, '/assets/') === 0 || strpos($__apxUri, '/public/assets/') === 0) {
    $__apxPos = strpos($__apxUri, '/assets/');
    $__apxRel = ltrim(substr($__apxUri, $__apxPos + strlen('/assets/')), '/');
    $__apxBase = realpath(APP_ROOT . '/public/assets');
    $__apxFile = ($__apxBase !== false) ? realpath($__apxBase . '/' . $__apxRel) : false;
    if ($__apxFile !== false
        && strncmp($__apxFile, $__apxBase . DIRECTORY_SEPARATOR, strlen($__apxBase . DIRECTORY_SEPARATOR)) === 0
        && is_file($__apxFile)) {
        $__apxExt = strtolower(pathinfo($__apxFile, PATHINFO_EXTENSION));
        $__apxMimes = [
            'css' => 'text/css', 'js' => 'application/javascript', 'mjs' => 'application/javascript',
            'json' => 'application/json', 'map' => 'application/json', 'svg' => 'image/svg+xml',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff' => 'font/woff',
            'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        ];
        header('Content-Type: ' . ($__apxMimes[$__apxExt] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=604800, immutable');
        header('X-Content-Type-Options: nosniff');
        if (ob_get_level()) { ob_end_clean(); }
        readfile($__apxFile);
        exit;
    }
    http_response_code(404);
    exit;
}

// 应用未安装时（缺 database.php 且非安装流程），引导至 install.php
if (!defined('APX_INSTALLING') && !is_file(APP_ROOT . '/config/database.php')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, 'install.php') === false && strpos($script, 'index.php') !== false) {
        header('Location: /install.php');
        exit;
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once APP_ROOT . '/app/Helpers/functions.php';

App\Core\ErrorHandler::register();
