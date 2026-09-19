<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 设置中心服务：两步验证（TOTP + 备份码）、自定义屏蔽词、屏蔽用户、免打扰时段、
 * 账号注销冷静期。所有敏感操作（改密/注销/关闭两步验证）要求二次验证当前密码或验证码。
 */
class SettingsService
{
    public const DELETE_GRACE_DAYS = 30;

    private static function db(): Database
    {
        return Database::instance();
    }

    // ------------------------------------------------------------------
    // 通用键值
    // ------------------------------------------------------------------

    public static function get(int $uid, string $key, ?string $default = null): ?string
    {
        $v = self::db()->column('SELECT value FROM user_settings WHERE user_id = ? AND `key` = ? LIMIT 1', [$uid, $key]);
        return is_string($v) ? $v : $default;
    }

    public static function set(int $uid, string $key, string $value): void
    {
        self::db()->statement(
            'INSERT INTO user_settings (user_id, `key`, value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$uid, $key, $value, now_utc(), now_utc()]
        );
    }

    public static function forget(int $uid, string $key): void
    {
        self::db()->delete('user_settings', 'user_id = ? AND `key` = ?', [$uid, $key]);
    }

    // ------------------------------------------------------------------
    // 两步验证
    // ------------------------------------------------------------------

    public static function totpStatus(int $uid): array
    {
        $row = self::db()->fetch('SELECT secret, confirmed_at FROM user_totp WHERE user_id = ? LIMIT 1', [$uid]);
        $remaining = (int) self::db()->column(
            'SELECT COUNT(*) FROM backup_codes WHERE user_id = ? AND used_at IS NULL',
            [$uid]
        );
        return [
            'enabled'          => $row !== null && !empty($row['confirmed_at']),
            'pending'          => $row !== null && empty($row['confirmed_at']),
            'has_secret'       => $row !== null,
            'backup_remaining' => $remaining,
        ];
    }

    /** 生成（或重置）待确认的 TOTP 密钥。 */
    public static function totpSetup(int $uid): array
    {
        $secret = self::randomBase32(32);
        self::db()->statement(
            'INSERT INTO user_totp (user_id, secret, label, algorithm, digits, period, confirmed_at, created_at)
             VALUES (?, ?, ?, \'sha1\', 6, 30, NULL, ?)
             ON DUPLICATE KEY UPDATE secret = VALUES(secret), confirmed_at = NULL, created_at = VALUES(created_at)',
            [$uid, $secret, 'APX', now_utc()]
        );
        $user = self::db()->fetch('SELECT username, email FROM users WHERE id = ? LIMIT 1', [$uid]);
        $account = $user['email'] ?? ($user['username'] ?? ('user' . $uid));
        return [
            'secret' => $secret,
            'uri'    => TotpService::provisioningUri($secret, (string) $account),
            'account' => (string) $account,
        ];
    }

    /** 校验验证码后启用两步验证，并返回一次性备份码明文。 */
    public static function totpEnable(int $uid, string $code): array
    {
        $row = self::db()->fetch('SELECT secret FROM user_totp WHERE user_id = ? LIMIT 1', [$uid]);
        if (!$row || !TotpService::verify((string) $row['secret'], $code)) {
            return ['ok' => false];
        }
        self::db()->update('user_totp', ['confirmed_at' => now_utc()], 'user_id = ?', [$uid]);
        self::db()->update('users', ['two_factor_enabled' => 1], 'id = ?', [$uid]);
        return ['ok' => true, 'codes' => self::generateBackupCodes($uid)];
    }

    public static function totpDisable(int $uid, string $code): bool
    {
        $row = self::db()->fetch('SELECT secret FROM user_totp WHERE user_id = ? AND confirmed_at IS NOT NULL LIMIT 1', [$uid]);
        if (!$row) {
            return false;
        }
        $valid = TotpService::verify((string) $row['secret'], $code) || self::consumeBackupCode($uid, $code);
        if (!$valid) {
            return false;
        }
        self::db()->delete('user_totp', 'user_id = ?', [$uid]);
        self::db()->delete('backup_codes', 'user_id = ?', [$uid]);
        self::db()->update('users', ['two_factor_enabled' => 0], 'id = ?', [$uid]);
        return true;
    }

    /** 重新生成备份码（需已启用）。 */
    public static function regenerateBackupCodes(int $uid): array
    {
        $status = self::totpStatus($uid);
        if (!$status['enabled']) {
            return [];
        }
        return self::generateBackupCodes($uid);
    }

    private static function generateBackupCodes(int $uid): array
    {
        $db = self::db();
        $db->delete('backup_codes', 'user_id = ?', [$uid]);
        $plain = [];
        for ($i = 0; $i < 10; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
            $plain[] = $code;
            $db->insert('backup_codes', [
                'user_id'    => $uid,
                'code_hash'  => password_hash(str_replace('-', '', $code), PASSWORD_DEFAULT),
                'created_at' => now_utc(),
            ]);
        }
        return $plain;
    }

    private static function consumeBackupCode(int $uid, string $code): bool
    {
        $normalized = strtoupper(str_replace('-', '', trim($code)));
        if ($normalized === '') {
            return false;
        }
        $rows = self::db()->fetchAll('SELECT id, code_hash FROM backup_codes WHERE user_id = ? AND used_at IS NULL', [$uid]);
        foreach ($rows as $r) {
            if (password_verify($normalized, (string) $r['code_hash'])) {
                self::db()->update('backup_codes', ['used_at' => now_utc()], 'id = ?', [$r['id']]);
                return true;
            }
        }
        return false;
    }

    private static function randomBase32(int $length): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // 自定义屏蔽词
    // ------------------------------------------------------------------

    public static function words(int $uid): array
    {
        return self::db()->fetchAll('SELECT id, word, created_at FROM user_muted_words WHERE user_id = ? ORDER BY id DESC', [$uid]);
    }

    public static function addWord(int $uid, string $word): bool
    {
        $word = trim($word);
        if ($word === '' || mb_strlen($word) > 64) {
            return false;
        }
        self::db()->statement(
            'INSERT INTO user_muted_words (user_id, word, created_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE word = VALUES(word)',
            [$uid, $word, now_utc()]
        );
        return true;
    }

    public static function removeWord(int $uid, int $id): bool
    {
        return self::db()->delete('user_muted_words', 'user_id = ? AND id = ?', [$uid, $id]) > 0;
    }

    public static function removeWordByText(int $uid, string $word): bool
    {
        $word = trim($word);
        if ($word === '') {
            return false;
        }
        return self::db()->delete('user_muted_words', 'user_id = ? AND word = ?', [$uid, $word]) > 0;
    }

    /** 供信息流调用的屏蔽词列表。 */
    public static function mutedWords(int $uid): array
    {
        $rows = self::db()->fetchAll('SELECT word FROM user_muted_words WHERE user_id = ?', [$uid]);
        return array_values(array_filter(array_map(fn($r) => (string) $r['word'], $rows), fn($w) => $w !== ''));
    }

    // ------------------------------------------------------------------
    // 屏蔽用户
    // ------------------------------------------------------------------

    public static function mutedUsers(int $uid): array
    {
        return self::db()->fetchAll(
            'SELECT m.target_id AS id, u.username, u.nickname, up.avatar
             FROM user_muted_users m
             JOIN users u ON u.id = m.target_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE m.user_id = ? ORDER BY m.id DESC',
            [$uid]
        );
    }

    public static function muteUser(int $uid, int $targetId): bool
    {
        if ($targetId <= 0 || $targetId === $uid) {
            return false;
        }
        self::db()->statement(
            'INSERT INTO user_muted_users (user_id, target_id, created_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)',
            [$uid, $targetId, now_utc()]
        );
        return true;
    }

    public static function unmuteUser(int $uid, int $targetId): bool
    {
        return self::db()->delete('user_muted_users', 'user_id = ? AND target_id = ?', [$uid, $targetId]) > 0;
    }

    public static function mutedUserIds(int $uid): array
    {
        $rows = self::db()->fetchAll('SELECT target_id FROM user_muted_users WHERE user_id = ?', [$uid]);
        return array_map(fn($r) => (int) $r['target_id'], $rows);
    }

    // ------------------------------------------------------------------
    // 黑名单
    // ------------------------------------------------------------------

    public static function blockedUsers(int $uid): array
    {
        return self::db()->fetchAll(
            'SELECT b.target_id AS id, u.username, u.nickname, up.avatar
             FROM blocks b
             JOIN users u ON u.id = b.target_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE b.user_id = ? ORDER BY b.id DESC',
            [$uid]
        );
    }

    public static function block(int $uid, int $targetId): bool
    {
        if ($targetId <= 0 || $targetId === $uid) {
            return false;
        }
        self::db()->statement(
            'INSERT INTO blocks (user_id, target_id, created_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)',
            [$uid, $targetId, now_utc()]
        );
        return true;
    }

    public static function unblock(int $uid, int $targetId): bool
    {
        return self::db()->delete('blocks', 'user_id = ? AND target_id = ?', [$uid, $targetId]) > 0;
    }

    // ------------------------------------------------------------------
    // 免打扰
    // ------------------------------------------------------------------

    public static function dnd(int $uid): array
    {
        return [
            'enabled' => self::get($uid, 'dnd_enabled', '0') === '1',
            'start'   => self::get($uid, 'dnd_start', '22:00') ?? '22:00',
            'end'     => self::get($uid, 'dnd_end', '08:00') ?? '08:00',
        ];
    }

    public static function setDnd(int $uid, bool $enabled, string $start, string $end): void
    {
        $start = preg_match('/^\d{1,2}:\d{2}$/', $start) ? $start : '22:00';
        $end   = preg_match('/^\d{1,2}:\d{2}$/', $end) ? $end : '08:00';
        self::set($uid, 'dnd_enabled', $enabled ? '1' : '0');
        self::set($uid, 'dnd_start', $start);
        self::set($uid, 'dnd_end', $end);
    }

    /** 当前是否处于免打扰时段（跨零点自动处理）。 */
    public static function isDndActive(int $uid): bool
    {
        $cfg = self::dnd($uid);
        if (!$cfg['enabled']) {
            return false;
        }
        $now = (int) date('H') * 60 + (int) date('i');
        $s = self::minutes($cfg['start']);
        $e = self::minutes($cfg['end']);
        if ($s === $e) {
            return true;
        }
        if ($s < $e) {
            return $now >= $s && $now < $e;
        }
        return $now >= $s || $now < $e;
    }

    private static function minutes(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    // ------------------------------------------------------------------
    // 账号注销（30 天冷静期）
    // ------------------------------------------------------------------

    public static function deletionState(int $uid): array
    {
        $row = self::db()->fetch('SELECT status, deleted_at FROM users WHERE id = ? LIMIT 1', [$uid]);
        $deletedAt = $row['deleted_at'] ?? null;
        return [
            'deleted'    => ($row['status'] ?? '') === 'deleting',
            'purge_at'   => $deletedAt,
            'grace_days' => self::DELETE_GRACE_DAYS,
        ];
    }

    public static function requestDeletion(int $uid, string $password): bool
    {
        $user = self::db()->fetch('SELECT password_hash FROM users WHERE id = ? LIMIT 1', [$uid]);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        self::db()->update('users', [
            'status'     => 'deleting',
            'deleted_at' => date('Y-m-d H:i:s', time() + self::DELETE_GRACE_DAYS * 86400),
        ], 'id = ?', [$uid]);
        return true;
    }

    /** 登录即撤销注销（在 authenticate 中调用）。 */
    public static function cancelDeletion(int $uid): void
    {
        self::db()->update('users', ['status' => 'active', 'deleted_at' => null], 'id = ?', [$uid]);
    }
}
