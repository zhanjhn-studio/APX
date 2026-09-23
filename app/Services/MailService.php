<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * 邮件服务。零依赖：未配置 SMTP 时自动降级为日志模式（写 storage/logs）。
 * 配置后通过 fsockopen + STARTTLS/SSL 自行发送，不引入 PHPMailer。
 */
class MailService
{
    public static function send(string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        $cfg = Config::all('mail');
        $devMode = !empty($cfg['dev_mode']) || empty($cfg['host']) || empty($cfg['username']);

        if ($devMode) {
            Logger::write('mail', 'DEV 模式未真实发送', [
                'to' => $to,
                'subject' => $subject,
                'text' => $bodyText ?: strip_tags($bodyHtml),
            ]);
            return true;
        }

        return self::smtpSend($cfg, $to, $subject, $bodyHtml, $bodyText);
    }

    /** 异步投递：交由队列 worker 发送（Redis 不可用时自动同步发送）。 */
    public static function queue(string $to, string $subject, string $bodyHtml, string $bodyText = ''): void
    {
        QueueService::push('mail', [
            'to'      => $to,
            'subject' => $subject,
            'html'    => $bodyHtml,
            'text'    => $bodyText,
        ]);
    }

    private static function smtpSend(array $cfg, string $to, string $subject, string $html, string $text): bool
    {
        $host = $cfg['host'];
        $port = (int) ($cfg['port'] ?? 465);
        $enc = $cfg['encryption'] ?? 'ssl';
        $transport = $enc === 'tls' ? 'tcp' : 'ssl';
        $context = $enc === 'ssl' ? stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]) : null;
        $fp = @stream_socket_client($transport . '://' . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context ?? stream_context_create());
        if (!$fp) {
            Logger::error('SMTP 连接失败', ['host' => $host, 'err' => $errstr]);
            return false;
        }
        $ok = true;
        $fail = function (string $cmd, string $line) use (&$ok) {
            if ($line[0] !== '2' && $line[0] !== '3') {
                $ok = false;
                Logger::error('SMTP 错误', ['cmd' => $cmd, 'resp' => trim($line)]);
            }
        };
        $fail('connect', fgets($fp, 512));
        fwrite($fp, 'EHLO apx' . "\r\n");
        $fail('ehlo', fgets($fp, 512));
        if ($enc === 'tls') {
            fwrite($fp, 'STARTTLS' . "\r\n");
            $fail('starttls', fgets($fp, 512));
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($fp, 'EHLO apx' . "\r\n");
            fgets($fp, 512);
        }
        fwrite($fp, 'AUTH LOGIN' . "\r\n");
        fgets($fp, 512);
        fwrite($fp, base64_encode($cfg['username']) . "\r\n");
        fgets($fp, 512);
        fwrite($fp, base64_encode($cfg['password']) . "\r\n");
        $fail('auth', fgets($fp, 512));
        fwrite($fp, 'MAIL FROM: <' . $cfg['from_address'] . '>' . "\r\n");
        $fail('mail_from', fgets($fp, 512));
        fwrite($fp, 'RCPT TO: <' . $to . '>' . "\r\n");
        $fail('rcpt_to', fgets($fp, 512));
        fwrite($fp, 'DATA' . "\r\n");
        fgets($fp, 512);
        $headers = "From: {$cfg['from_name']} <{$cfg['from_address']}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        fwrite($fp, $headers . $html . "\r\n.\r\n");
        $fail('data', fgets($fp, 512));
        fwrite($fp, 'QUIT' . "\r\n");
        fclose($fp);
        return $ok;
    }
}
