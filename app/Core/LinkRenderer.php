<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 安全的富文本渲染器。把纯文本中的 URL、#话题#、@提及 转为安全锚点。
 * 危险协议（javascript:/data:/vbscript:/file:）降级为纯文本。
 *
 * 完整风险判定（同形异义、IP 直访、私有网段等）由 LinkGuardService 完成，
 * 此处只做基础分类；前端 link-guard.js 负责外链二次确认弹窗。
 */
class LinkRenderer
{
    public static function render(string $text, bool $autoLinks = true): string
    {
        if (!$autoLinks) {
            return nl2br(e($text), false);
        }
        $html = '';
        foreach (self::tokenize($text) as $part) {
            switch ($part['type']) {
                case 'url':
                    $html .= self::anchor($part['value']);
                    break;
                case 'topic':
                    $html .= self::topicAnchor($part['value']);
                    break;
                case 'mention':
                    $html .= self::mentionAnchor($part['value']);
                    break;
                default:
                    $html .= e($part['value']);
            }
        }
        return nl2br($html, false);
    }

    /**
     * 单次扫描，按出现顺序返回 [type, value, offset]。
     * type: text | url | topic | mention
     */
    private static function tokenize(string $text): array
    {
        $pattern = '~(https?://[^\s<>"]+)|(#[^#\s]{1,50}#)|(@[A-Za-z0-9_]{3,32})~u';
        $out = [];
        $last = 0;
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return [['type' => 'text', 'value' => $text]];
        }
        foreach ($matches as $m) {
            $start = (int) $m[0][1];
            $token = (string) $m[0][0];
            if ($start > $last) {
                $out[] = ['type' => 'text', 'value' => substr($text, $last, $start - $last)];
            }
            if (isset($m[1]) && $m[1][1] >= 0) {
                $out[] = ['type' => 'url', 'value' => $token];
            } elseif (isset($m[2]) && $m[2][1] >= 0) {
                $out[] = ['type' => 'topic', 'value' => $token];
            } elseif (isset($m[3]) && $m[3][1] >= 0) {
                $out[] = ['type' => 'mention', 'value' => $token];
            } else {
                $out[] = ['type' => 'text', 'value' => $token];
            }
            $last = $start + strlen($token);
        }
        if ($last < strlen($text)) {
            $out[] = ['type' => 'text', 'value' => substr($text, $last)];
        }
        return $out;
    }

    private static function anchor(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return e($url); // 危险协议降级
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (self::isInternal($host)) {
            return '<a href="' . e($url) . '" class="apx-link-internal" data-internal="1">' . e($url) . '</a>';
        }
        return '<a href="' . e($url) . '" class="apx-link-external" data-external="1" data-url="' . e($url) .
            '" target="_blank" rel="noopener noreferrer nofollow">' . e($url) . '</a>';
    }

    private static function topicAnchor(string $token): string
    {
        $name = trim($token, '#');
        if ($name === '') {
            return e($token);
        }
        $href = self::base() . '/topic/' . rawurlencode(self::slugify($name));
        return '<a href="' . e($href) . '" class="apx-link-topic">' . e($token) . '</a>';
    }

    private static function slugify(string $name): string
    {
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $name);
        $s = mb_strtolower(trim($s, '-'), 'UTF-8');
        return $s === '' ? 't' . substr(md5($name), 0, 8) : $s;
    }

    private static function mentionAnchor(string $token): string
    {
        $username = substr($token, 1);
        if ($username === '') {
            return e($token);
        }
        $href = self::base() . '/profile/' . rawurlencode($username);
        return '<a href="' . e($href) . '" class="apx-link-mention">' . e($token) . '</a>';
    }

    private static function base(): string
    {
        return rtrim(Config::get('app.base_url') ?: '', '/');
    }

    private static function isInternal(?string $host): bool
    {
        if (!$host) {
            return false;
        }
        $base = Config::get('app.base_url') ?: '';
        if ($base) {
            $h = parse_url($base, PHP_URL_HOST);
            if ($h && strcasecmp($h, (string) $host) === 0) {
                return true;
            }
        }
        $allowed = LinkGuardService::internalHosts();
        foreach ($allowed as $a) {
            if (strcasecmp($a, (string) $host) === 0) {
                return true;
            }
        }
        return false;
    }
}
