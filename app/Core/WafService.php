<?php
declare(strict_types=1);
namespace App\Core;

use App\Core\Database;
use App\Services\SiteSettingsService;

/**
 * WAF 评分服务。加载规则 → 扫描请求各维度 → 累加分数 → 判定处置。
 * 默认观察模式只记录，防御模式按阈值拦截。
 */
class WafService
{
    private static array $rules = [];
    private static bool $loaded = false;

    public static function loadRules(): array
    {
        if (!self::$loaded) {
            self::$rules = Cache::remember('waf_rules', 600, fn() =>
                Database::instance()->fetchAll('SELECT * FROM waf_rules WHERE enabled = 1'));
            self::$loaded = true;
        }
        return self::$rules;
    }

    public static function evaluate(Request $req): array
    {
        $rules = self::loadRules();
        $haystacks = [
            'uri'     => $req->uri(),
            'get'     => http_build_query($req->get()),
            'post'    => http_build_query($req->post()),
            'cookie'  => http_build_query($req->cookie ?? []),
            'ua'      => $req->userAgent(),
            'referer' => $req->header('Referer') ?? '',
        ];
        $score = 0;
        $hits = [];
        foreach ($rules as $rule) {
            $pattern = '~' . $rule['pattern'] . '~iu';
            foreach ($haystacks as $source => $text) {
                if ($text === '' || !@preg_match($pattern, $text)) {
                    continue;
                }
                $score += (int) $rule['score'];
                $hits[] = ['rule' => $rule['name'], 'category' => $rule['category'], 'source' => $source];
                self::bumpHit((int) $rule['id']);
                break;
            }
        }
        return ['score' => $score, 'hits' => $hits, 'action' => self::verdict($score)];
    }

    private static function verdict(int $score): string
    {
        $th = Config::get('security.waf.thresholds', ['log' => 1, 'challenge' => 5, 'block' => 10, 'ban' => 20]);
        if ($score >= $th['ban']) return 'ban';
        if ($score >= $th['block']) return 'block';
        if ($score >= $th['challenge']) return 'challenge';
        return 'log';
    }

    public static function isBanned(string $ip): bool
    {
        $row = Database::instance()->fetch(
            'SELECT id FROM ip_bans WHERE (ip = ? OR ip IS NULL) AND (expires_at IS NULL OR expires_at > ?) LIMIT 1',
            [$ip, now_utc()]
        );
        return $row !== null;
    }

    /** 运行模式：后台设置（settings.waf_mode）优先，缺省回落 config/security.php。 */
    public static function mode(): string
    {
        $mode = SiteSettingsService::get('waf_mode', null);
        if ($mode === null || $mode === '') {
            $mode = (string) Config::get('security.waf.mode', 'observe');
        }
        return $mode === 'defense' ? 'defense' : 'observe';
    }

    public static function log(string $ip, ?int $userId, array $result, string $payload): void
    {
        $mode = self::mode();
        $action = $mode === 'defense' ? $result['action'] : 'log';
        Database::instance()->insert('waf_logs', [
            'ip' => $ip,
            'user_id' => $userId,
            'uri' => mb_substr($payload !== '' ? $payload : '', 0, 512),
            'rule_name' => $result['hits'][0]['rule'] ?? null,
            'score' => $result['score'],
            'action' => $action,
            'payload_snapshot' => mb_substr($payload, 0, 512),
            'user_agent' => '',
            'created_at' => now_utc(),
        ]);
        if ($action === 'ban') {
            self::applyBan($ip, $userId, $result['score']);
        }
    }

    private static function applyBan(string $ip, ?int $userId, int $score): void
    {
        // 阶梯封禁：依据当日命中次数选择时长
        $count = (int) Database::instance()->column(
            'SELECT COUNT(*) FROM waf_logs WHERE ip = ? AND action = ? AND created_at > ?',
            [$ip, 'ban', date('Y-m-d H:i:s', time() - 86400)]
        );
        $tiers = Config::get('security.waf.ban_tiers', [300, 3600, 86400]);
        $seconds = $tiers[min($count, count($tiers) - 1)];
        Database::instance()->insert('ip_bans', [
            'ip' => $ip,
            'user_id' => $userId,
            'reason' => 'waf.auto',
            'expires_at' => date('Y-m-d H:i:s', time() + $seconds),
            'created_at' => now_utc(),
        ]);
    }

    private static function bumpHit(int $id): void
    {
        Database::instance()->statement('UPDATE waf_rules SET hit_count = hit_count + 1 WHERE id = ?', [$id]);
    }
}
