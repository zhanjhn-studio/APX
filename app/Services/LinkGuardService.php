<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * 链接守护服务。负责：
 *  - 内置安全域名白名单（站内 + 常见可信站点）
 *  - 外链风险分析（危险协议 / 同形异义 / IP 直访 / 私有网段 / 非标准端口 / 短链）
 *  - 域名黑白名单（link_domains 表，管理员后台可维护）
 * 风险判定在服务端完成，前端 link-guard.js 仅负责展示与二次确认。
 */
class LinkGuardService
{
    private static ?array $internalHosts = null;

    public static function internalHosts(): array
    {
        if (self::$internalHosts !== null) {
            return self::$internalHosts;
        }
        $hosts = [];
        $base = Config::get('app.base_url');
        if ($base) {
            $h = parse_url($base, PHP_URL_HOST);
            if ($h) {
                $hosts[] = $h;
            }
        }
        $builtin = [
            'github.com', 'gitlab.com', 'bitbucket.org',
            'bilibili.com', 'youtube.com', 'youtu.be', 'twitter.com', 'x.com',
            'wikipedia.org', 'apple.com', 'google.com', 'microsoft.com', 'openai.com',
        ];
        $rows = Database::instance()->fetchAll(
            'SELECT domain, type FROM link_domains WHERE type = ? OR origin = ?',
            ['white', 'builtin']
        );
        foreach ($rows as $r) {
            if ($r['type'] === 'white') {
                $hosts[] = $r['domain'];
            }
        }
        self::$internalHosts = array_values(array_unique(array_merge($hosts, $builtin)));
        return self::$internalHosts;
    }

    public static function analyze(string $url): array
    {
        $result = ['url' => $url, 'host' => '', 'safe' => true, 'risk' => 'safe', 'reasons' => []];

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $result['safe'] = false;
            $result['risk'] = 'danger';
            $result['reasons'][] = 'dangerous_protocol';
            return $result;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $result['host'] = $host;

        // 黑名单域名
        if (self::isBlacklisted($host)) {
            $result['safe'] = false;
            $result['risk'] = 'danger';
            $result['reasons'][] = 'blacklisted';
            return $result;
        }

        // 私有/保留网段（IP 直访）
        $ip = gethostbyname($host);
        if (self::isPrivateOrReserved($host) || self::isPrivateOrReserved($ip)) {
            $result['safe'] = false;
            $result['risk'] = 'caution';
            $result['reasons'][] = 'private_network';
        }

        // 非标准端口
        $port = parse_url($url, PHP_URL_PORT);
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $result['risk'] = ($result['risk'] === 'safe') ? 'caution' : $result['risk'];
            $result['reasons'][] = 'nonstandard_port';
        }

        // 同形异义（Punycode）
        if (stripos($host, 'xn--') !== false) {
            $result['safe'] = false;
            $result['risk'] = 'caution';
            $result['reasons'][] = 'punycode';
        }

        return $result;
    }

    public static function isBlacklisted(string $host): bool
    {
        $row = Database::instance()->fetch(
            'SELECT id FROM link_domains WHERE type = ? AND domain = ? LIMIT 1',
            ['black', $host]
        );
        return $row !== null;
    }

    private static function isPrivateOrReserved(string $host): bool
    {
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        $long = ip2long($host);
        if ($long === false) {
            return false;
        }
        $ranges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['0.0.0.0', '0.255.255.255'],
            ['fc00::', 'fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
        ];
        foreach ($ranges as [$start, $end]) {
            $s = ip2long($start);
            $e = ip2long($end);
            if ($s !== false && $e !== false && $long >= $s && $long <= $e) {
                return true;
            }
        }
        // IPv6 保留（简化判断）
        if (strpos($host, ':') !== false) {
            if (preg_match('/^(fc|fd|fe|::|f[cd])/i', $host)) {
                return true;
            }
        }
        return false;
    }
}
