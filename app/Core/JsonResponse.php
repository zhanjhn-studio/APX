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
        // 服务端把 i18n key 翻成可读文案，避免任何客户端漏翻译时把裸 key（如 auth.login.empty / validation.username）暴露给用户
        $out = [
            'code'    => $code,
            'message' => I18n::translate($message),
            'data'    => $data,
            'errors'  => self::translateErrors($errors),
            'csrf'    => Csrf::token(),
        ];
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        exit;
    }

    /** errors 字段的值可能也是 i18n key，统一翻译为可读文案 */
    private static function translateErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $k => $v) {
            $out[$k] = is_string($v) ? I18n::translate($v) : $v;
        }
        return $out;
    }
}
