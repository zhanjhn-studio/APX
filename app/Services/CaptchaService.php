<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Session;

/**
 * 自研 GD 图形验证码。代码存于服务端会话，输出 PNG。零依赖、零字体文件。
 */
class CaptchaService
{
    public const SESSION_KEY = 'captcha_code';

    /** 生成验证码并存入会话，返回明文（仅供调试，正常只输出图片）。 */
    public static function generate(int $length = 4): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        Session::set(self::SESSION_KEY, $code);
        return $code;
    }

    public static function verify(string $input): bool
    {
        $expected = Session::get(self::SESSION_KEY);
        if (!$expected) {
            return false;
        }
        Session::forget(self::SESSION_KEY);
        return hash_equals($expected, strtoupper(trim($input)));
    }

    /** 输出 PNG 验证码图片。 */
    public static function image(string $code, int $w = 120, int $h = 44): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            http_response_code(500);
            echo 'GD not available';
            return;
        }
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 22, 27, 34);
        imagefill($img, 0, 0, $bg);
        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, random_int(80, 160), random_int(80, 160), random_int(160, 230));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        // 字符
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $c = imagecolorallocate($img, random_int(180, 255), random_int(180, 255), random_int(200, 255));
            $size = random_int(18, 24);
            $x = 12 + $i * ($w / $len);
            $y = random_int(26, 34);
            imagestring($img, 5, (int) $x, (int) $y, $code[$i], $c);
        }
        // 噪点
        for ($i = 0; $i < 40; $i++) {
            $c = imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200));
            imagesetpixel($img, random_int(0, $w), random_int(0, $h), $c);
        }
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        imagepng($img);
        imagedestroy($img);
    }
}
