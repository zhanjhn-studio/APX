<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/**
 * 登录设备与会话管理。
 * 以 PHP 会话 ID 为主键在 sessions 表登记设备，支持信任标记、远程下线、
 * 新设备登录异常提醒，以及通过「会话纪元」在服务端强制失效已下线的登录态。
 */
class DeviceService
{
    private const SETTING_EPOCH = 'session_epoch';

    private static function db(): Database
    {
        return Database::instance();
    }

    public static function sessionId(): string
    {
        return (string) session_id();
    }

    public static function fingerprint(): string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return substr(hash('sha256', $ua), 0, 32);
    }

    /** 由 User-Agent 推断可读设备名。 */
    public static function deviceName(string $ua): string
    {
        $os = '未知设备';
        foreach ([
            'Windows NT'  => 'Windows',
            'Macintosh'   => 'macOS',
            'iPhone'      => 'iPhone',
            'iPad'        => 'iPad',
            'Android'     => 'Android',
            'Linux'       => 'Linux',
            'HarmonyOS'   => 'HarmonyOS',
        ] as $needle => $label) {
            if (stripos($ua, $needle) !== false) {
                $os = $label;
                break;
            }
        }
        $browser = '浏览器';
        foreach ([
            'Edg/'      => 'Edge',
            'OPR/'      => 'Opera',
            'Chrome/'   => 'Chrome',
            'Firefox/'  => 'Firefox',
            'Safari/'   => 'Safari',
            'MicroMessenger' => 'WeChat',
        ] as $needle => $label) {
            if (stripos($ua, $needle) !== false) {
                $browser = $label;
                break;
            }
        }
        return $os . ' · ' . $browser;
    }

    /**
     * 登录成功后登记当前设备。首次出现的指纹触发异常登录提醒。
     */
    public static function register(int $userId): void
    {
        $sid = self::sessionId();
        if ($sid === '') {
            return;
        }
        $db = self::db();
        $fp = self::fingerprint();
        $existing = $db->fetch('SELECT id FROM sessions WHERE id = ? LIMIT 1', [$sid]);
        if (!$existing) {
            $known = $db->fetch('SELECT id FROM sessions WHERE user_id = ? AND device_fingerprint = ? LIMIT 1', [$userId, $fp]);
            $trusted = $known ? 1 : 0;
            $db->insert('sessions', [
                'id'                 => $sid,
                'user_id'            => $userId,
                'ip'                 => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent'         => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'device_fingerprint' => $fp,
                'trusted'            => $trusted,
                'last_active_at'     => now_utc(),
                'created_at'         => now_utc(),
            ]);
            if (!$known) {
                NotificationService::notifySystem(
                    $userId,
                    'notification.new_device',
                    self::deviceName((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                    null,
                    'session'
                );
            }
        } else {
            self::touch($userId);
        }
        Session::set('_epoch', self::epoch($userId));
    }

    /** 刷新当前会话活跃时间（节流：同一分钟只写一次）。 */
    public static function touch(int $userId): void
    {
        $sid = self::sessionId();
        if ($sid === '') {
            return;
        }
        $last = Session::get('_touched_at', 0);
        if (time() - (int) $last < 60) {
            return;
        }
        Session::set('_touched_at', time());
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $affected = self::db()->statement(
            'UPDATE `sessions` SET `last_active_at` = ?, `ip` = ? WHERE `id` = ? AND `user_id` = ?',
            [now_utc(), $ip, $sid, $userId]
        )->rowCount();
        if ($affected === 0) {
            // 会话 ID 可能已轮换，补登当前会话（同一设备不重复提醒）
            self::db()->statement(
                'INSERT INTO `sessions` (`id`, `user_id`, `ip`, `user_agent`, `device_fingerprint`, `trusted`, `last_active_at`, `created_at`)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE `last_active_at` = VALUES(`last_active_at`)',
                [
                    $sid, $userId, $ip,
                    mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    self::fingerprint(), now_utc(), now_utc(),
                ]
            );
        }
    }

    public static function list(int $userId): array
    {
        $rows = self::db()->fetchAll(
            'SELECT id, ip, user_agent, trusted, last_active_at, created_at
             FROM sessions WHERE user_id = ? ORDER BY last_active_at DESC',
            [$userId]
        );
        $current = self::sessionId();
        foreach ($rows as &$r) {
            $r['is_current'] = $r['id'] === $current;
            $r['device'] = self::deviceName((string) $r['user_agent']);
            $r['trusted'] = (int) $r['trusted'] === 1;
        }
        unset($r);
        return $rows;
    }

    public static function trust(int $userId, string $id, bool $trusted): bool
    {
        $row = self::db()->fetch('SELECT id FROM sessions WHERE id = ? AND user_id = ? LIMIT 1', [$id, $userId]);
        if (!$row) {
            return false;
        }
        self::db()->update('sessions', ['trusted' => $trusted ? 1 : 0], 'id = ?', [$id]);
        return true;
    }

    /** 下线指定设备（当前设备则同时退出登录）。 */
    public static function revoke(int $userId, string $id): bool
    {
        $row = self::db()->fetch('SELECT id FROM sessions WHERE id = ? AND user_id = ? LIMIT 1', [$id, $userId]);
        if (!$row) {
            return false;
        }
        self::db()->delete('sessions', 'id = ?', [$id]);
        if ($id === self::sessionId()) {
            self::bumpEpoch($userId);
            Session::logout();
        }
        return true;
    }

    /** 移除当前会话的设备记录（退出登录时调用）。 */
    public static function removeCurrent(): void
    {
        $sid = self::sessionId();
        if ($sid !== '') {
            self::db()->delete('sessions', 'id = ?', [$sid]);
        }
    }

    /** 下线其它所有设备（保留当前）。 */
    public static function revokeOthers(int $userId): int
    {
        $sid = self::sessionId();
        $count = self::db()->delete('sessions', 'user_id = ? AND id != ?', [$userId, $sid]);
        $newEpoch = self::bumpEpoch($userId);
        Session::set('_epoch', $newEpoch);
        return $count;
    }

    /** 当前用户被信任的设备数量。 */
    public static function trustedCount(int $userId): int
    {
        return (int) self::db()->column('SELECT COUNT(*) FROM sessions WHERE user_id = ? AND trusted = 1', [$userId]);
    }

    public static function epoch(int $userId): string
    {
        $v = self::db()->column(
            'SELECT value FROM user_settings WHERE user_id = ? AND `key` = ? LIMIT 1',
            [$userId, self::SETTING_EPOCH]
        );
        return is_string($v) && $v !== '' ? $v : '0';
    }

    public static function bumpEpoch(int $userId): string
    {
        $epoch = bin2hex(random_bytes(8));
        self::db()->statement(
            'INSERT INTO user_settings (user_id, `key`, value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$userId, self::SETTING_EPOCH, $epoch, now_utc(), now_utc()]
        );
        return $epoch;
    }

    /** 会话纪元校验：远程下线后，被下线的登录态立即失效。 */
    public static function assertEpoch(int $userId): bool
    {
        $sessionEpoch = (string) (Session::get('_epoch') ?? '0');
        return hash_equals(self::epoch($userId), $sessionEpoch);
    }
}
