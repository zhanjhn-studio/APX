<?php
declare(strict_types=1);
namespace App\Services;

/**
 * 自研 TOTP（RFC 6238 / HMAC-SHA1），兼容 Google / 微软验证器。无第三方依赖。
 */
class TotpService
{
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $key = self::base32Decode($secret);
        if ($key === '' || !ctype_digit($code)) {
            return false;
        }
        $period = 30;
        $digits = 6;
        $counter = (int) floor(time() / $period);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals((string) self::hotp($key, $counter + $i, $digits), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function provisioningUri(string $secret, string $account, string $issuer = 'APX'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&period=30&digits=6';
    }

    private static function hotp(string $key, int $counter, int $digits): string
    {
        $msg = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = (ord($hash[$offset]) & 0x7F) << 24
            | (ord($hash[$offset + 1]) & 0xFF) << 16
            | (ord($hash[$offset + 2]) & 0xFF) << 8
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($bin % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $c) {
            $idx = strpos($base32, $c);
            if ($idx === false) {
                continue;
            }
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
