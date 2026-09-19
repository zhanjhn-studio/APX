<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\CaptchaService;

/**
 * 验证码图片端点。直接输出 PNG，无 HTML 响应。
 */
class CaptchaController
{
    public function show(): void
    {
        $code = CaptchaService::generate();
        CaptchaService::image($code);
        exit;
    }
}
