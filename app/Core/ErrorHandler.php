<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 统一错误处理。生产环境关闭 display_errors，记录日志并返回友好页面。
 * 致命错误兜底，绝不暴露路径与 SQL。
 */
class ErrorHandler
{
    public static function register(): void
    {
        $display = Config::get('app.debug', false);
        ini_set('display_errors', $display ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        Logger::error('PHP Error', ['errno' => $errno, 'msg' => $errstr, 'file' => basename($errfile), 'line' => $errline]);
        if (Config::get('app.debug', false)) {
            echo "<pre>Error: {$errstr} in " . basename($errfile) . ":{$errline}</pre>";
        }
        return true;
    }

    public static function handleException(\Throwable $e): void
    {
        Logger::error('Uncaught Exception', [
            'msg'  => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'type' => get_class($e),
        ]);
        if (is_ajax()) {
            JsonResponse::fail(500, 'server.error', []);
        }
        http_response_code(500);
        echo self::page(500, 'common.error.500', 'common.error.500.detail');
        exit;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            Logger::error('Fatal Error', ['msg' => $error['message'], 'file' => basename($error['file']), 'line' => $error['line']]);
        }
    }

    public static function abort(int $code, string $message = ''): void
    {
        $titles = [403 => 'common.error.403', 404 => 'common.error.404', 500 => 'common.error.500'];
        $details = [403 => 'common.error.403.detail', 404 => 'common.error.404.detail', 500 => 'common.error.500.detail'];
        if (is_ajax()) {
            JsonResponse::fail($code, $message !== '' ? $message : ($titles[$code] ?? 'common.error'));
        }
        http_response_code($code);
        echo self::page($code, $titles[$code] ?? 'common.error', $details[$code] ?? '');
        exit;
    }

    private static function page(int $code, string $titleKey, string $detailKey): string
    {
        $title = function_exists('__') ? __($titleKey) : $titleKey;
        $detail = function_exists('__') ? __($detailKey) : $detailKey;
        return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">' .
            '<meta name="viewport" content="width=device-width,initial-scale=1">' .
            '<title>' . e($code) . ' · APX</title>' .
            '<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:#0E1116;color:#E8ECF2;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.card{text-align:center;padding:48px}.code{font-size:64px;font-weight:700;background:linear-gradient(135deg,#6366F1,#8B5CF6,#22D3EE);-webkit-background-clip:text;background-clip:text;color:transparent}.btn{margin-top:24px;display:inline-block;padding:10px 24px;border-radius:12px;background:#6366F1;color:#fff;text-decoration:none}</style>' .
            '</head><body><div class="card"><div class="code">' . e((string) $code) . '</div>' .
            '<h1>' . e($title) . '</h1><p style="color:#9AA5B4">' . e($detail) . '</p>' .
            '<a class="btn" href="/">返回首页</a></div></body></html>';
    }
}
