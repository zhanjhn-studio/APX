<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 统一 JSON 响应格式：{code, message, data, errors, csrf}
 * code = 0 表示成功；非 0 为业务错误码。
 */
class JsonResponse
{
    public static function ok(array $data = [], string $message = 'ok'): void
    {
        self::emit(0, $message, $data, []);
    }

    public static function fail(int $code, string $message, array $errors = []): void
    {
        self::emit($code, $message, null, $errors);
    }

    public static function created(array $data = [], string $message = 'created'): void
    {
        self::emit(0, $message, $data, []);
    }

    private static function emit(int $code, string $message, $data, array $errors): void
    {
        $http = $code === 0 ? 200 : ($code >= 400 && $code < 600 ? $code : 400);
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        $out = [
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
            'csrf'    => Csrf::token(),
        ];
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        exit;
    }
}
