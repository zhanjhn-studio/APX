<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 举报服务。用户侧提交（公开内容）+ 后台查看与处理（warn / delete / ban / dismiss）。
 * 处理动作一律写入 report_actions 留痕，并同步 admin_logs。
 */
class ReportService
{
    public const TYPES = ['user', 'post', 'comment', 'group', 'message'];
    public const REASONS = ['spam', 'harassment', 'porn', 'violence', 'illegal', 'misinfo', 'other'];
    public const ACTIONS = ['warn', 'delete', 'ban', 'dismiss'];

    private static function db(): Database
    {
        return Database::instance();
    }

    /** 用户侧提交举报。返回 ['ok'=>bool,'error'=>?string]。 */
    public static function submit(int $reporterId, string $targetType, int $targetId, string $reason, string $detail): array
    {
        if (!in_array($targetType, self::TYPES, true) || $targetId <= 0) {
            return ['ok' => false, 'error' => 'validation.invalid'];
        }
        if (!in_array($reason, self::REASONS, true)) {
            return ['ok' => false, 'error' => 'validation.invalid'];
        }
        if (!self::targetExists($targetType, $targetId)) {
            return ['ok' => false, 'error' => 'report.not_found'];
        }
        if (self::isSelf($reporterId, $targetType, $targetId)) {
            return ['ok' => false, 'error' => 'report.self'];
        }
        $dup = self::db()->fetch(
            "SELECT id FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status IN ('pending','processing') LIMIT 1",
            [$reporterId, $targetType, $targetId]
        );
        if ($dup) {
            return ['ok' => false, 'error' => 'report.duplicate'];
        }
        self::db()->insert('reports', [
            'reporter_id' => $reporterId,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'reason'      => $reason,
            'detail'      => mb_substr(trim($detail), 0, 500),
            'status'      => 'pending',
            'created_at'  => now_utc(),
        ]);
        return ['ok' => true, 'error' => null];
    }

    private static function isSelf(int $userId, string $type, int $targetId): bool
    {
        $owner = self::ownerId($type, $targetId);
        return $owner !== null && $owner === $userId && $type !== 'group';
    }

    public static function ownerId(string $type, int $targetId): ?int
    {
        $db = self::db();
        switch ($type) {
            case 'user':
                return $targetId;
            case 'post':
                $r = $db->fetch('SELECT user_id FROM posts WHERE id = ? LIMIT 1', [$targetId]);
                return $r ? (int) $r['user_id'] : null;
            case 'comment':
                $r = $db->fetch('SELECT user_id FROM post_comments WHERE id = ? LIMIT 1', [$targetId]);
                return $r ? (int) $r['user_id'] : null;
            case 'group':
                $r = $db->fetch('SELECT owner_id FROM groups WHERE id = ? LIMIT 1', [$targetId]);
                return $r ? (int) $r['owner_id'] : null;
            case 'message':
                $r = $db->fetch('SELECT sender_id FROM messages WHERE id = ? LIMIT 1', [$targetId]);
                return $r && $r['sender_id'] !== null ? (int) $r['sender_id'] : null;
        }
        return null;
    }

    private static function targetExists(string $type, int $targetId): bool
    {
        $db = self::db();
        switch ($type) {
            case 'user':
                return $db->fetch('SELECT id FROM users WHERE id = ? LIMIT 1', [$targetId]) !== null;
            case 'post':
                return $db->fetch('SELECT id FROM posts WHERE id = ? LIMIT 1', [$targetId]) !== null;
            case 'comment':
                return $db->fetch('SELECT id FROM post_comments WHERE id = ? LIMIT 1', [$targetId]) !== null;
            case 'group':
                return $db->fetch('SELECT id FROM groups WHERE id = ? LIMIT 1', [$targetId]) !== null;
            case 'message':
                return $db->fetch('SELECT id FROM messages WHERE id = ? LIMIT 1', [$targetId]) !== null;
        }
        return false;
    }

    public static function counts(): array
    {
        $db = self::db();
        return [
            'pending'    => (int) $db->column("SELECT COUNT(*) FROM reports WHERE status = 'pending'"),
            'processing' => (int) $db->column("SELECT COUNT(*) FROM reports WHERE status = 'processing'"),
            'resolved'   => (int) $db->column("SELECT COUNT(*) FROM reports WHERE status = 'resolved'"),
            'rejected'   => (int) $db->column("SELECT COUNT(*) FROM reports WHERE status = 'rejected'"),
        ];
    }

    /** 后台列表（含目标摘要）。 */
    public static function list(string $status = 'pending', string $type = '', int $page = 1): array
    {
        $db = self::db();
        $where = ['1 = 1'];
        $params = [];
        if ($status !== '' && in_array($status, ['pending', 'processing', 'resolved', 'rejected'], true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($type !== '' && in_array($type, self::TYPES, true)) {
            $where[] = 'r.target_type = ?';
            $params[] = $type;
        }
        $w = implode(' AND ', $where);
        $perPage = 20;
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $db->column("SELECT COUNT(*) FROM reports r WHERE $w", $params);
        $items = $db->fetchAll(
            "SELECT r.id, r.reporter_id, r.target_type, r.target_id, r.reason, r.detail, r.status, r.created_at,
                    u.username AS reporter_username, u.nickname AS reporter_nickname
             FROM reports r JOIN users u ON u.id = r.reporter_id
             WHERE $w ORDER BY FIELD(r.status,'pending','processing','resolved','rejected'), r.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );
        foreach ($items as &$it) {
            $it['target'] = self::summarize((string) $it['target_type'], (int) $it['target_id']);
            $it['actions'] = $db->fetchAll(
                "SELECT a.action, a.note, a.created_at, u.username AS admin_username
                 FROM report_actions a LEFT JOIN users u ON u.id = a.admin_id
                 WHERE a.report_id = ? ORDER BY a.id DESC",
                [(int) $it['id']]
            );
        }
        unset($it);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** 目标摘要（用于后台快速判断，不返回被举报内容的全文）。 */
    public static function summarize(string $type, int $targetId): array
    {
        $db = self::db();
        switch ($type) {
            case 'user':
                $r = $db->fetch('SELECT id, username, nickname, status FROM users WHERE id = ? LIMIT 1', [$targetId]);
                return $r ? ['label' => '@' . $r['username'], 'meta' => $r['nickname'] . ' · ' . $r['status'], 'user_id' => (int) $r['id']] : ['label' => '#', 'meta' => '', 'user_id' => 0];
            case 'post':
                $r = $db->fetch('SELECT p.id, p.user_id, p.body, p.deleted_at, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ? LIMIT 1', [$targetId]);
                return $r ? ['label' => mb_substr((string) $r['body'], 0, 60), 'meta' => '@' . $r['username'] . ($r['deleted_at'] ? ' · 已删除' : ''), 'user_id' => (int) $r['user_id']] : ['label' => '#', 'meta' => '', 'user_id' => 0];
            case 'comment':
                $r = $db->fetch('SELECT c.id, c.user_id, c.body, u.username FROM post_comments c JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1', [$targetId]);
                return $r ? ['label' => mb_substr((string) $r['body'], 0, 60), 'meta' => '@' . $r['username'], 'user_id' => (int) $r['user_id']] : ['label' => '#', 'meta' => '', 'user_id' => 0];
            case 'group':
                $r = $db->fetch('SELECT id, name, slug, status FROM groups WHERE id = ? LIMIT 1', [$targetId]);
                return $r ? ['label' => $r['name'], 'meta' => '/' . $r['slug'] . ' · ' . $r['status'], 'user_id' => 0] : ['label' => '#', 'meta' => '', 'user_id' => 0];
            case 'message':
                return ['label' => '[私聊消息]', 'meta' => '', 'user_id' => self::ownerId('message', $targetId) ?? 0];
        }
        return ['label' => '#', 'meta' => '', 'user_id' => 0];
    }

    /**
     * 处理举报。$action ∈ warn|delete|ban|dismiss。
     * delete：软删被举报的动态/评论；ban：封禁目标作者；warn：发系统通知警告；dismiss：驳回。
     */
    public static function handle(int $reportId, string $action, string $note = ''): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            return ['ok' => false, 'error' => 'validation.invalid'];
        }
        $report = self::db()->fetch('SELECT * FROM reports WHERE id = ? LIMIT 1', [$reportId]);
        if (!$report) {
            return ['ok' => false, 'error' => 'report.not_found'];
        }
        $adminId = (int) (AuthService::userId() ?? 0);
        $type = (string) $report['target_type'];
        $targetId = (int) $report['target_id'];
        $ownerId = self::ownerId($type, $targetId);

        switch ($action) {
            case 'delete':
                if ($type === 'post') {
                    self::db()->update('posts', ['deleted_at' => now_utc()], 'id = ?', [$targetId]);
                } elseif ($type === 'comment') {
                    self::db()->update('post_comments', ['deleted_at' => now_utc()], 'id = ?', [$targetId]);
                } elseif ($type === 'group') {
                    self::db()->update('groups', ['status' => 'disbanded'], 'id = ?', [$targetId]);
                }
                break;
            case 'ban':
                if ($ownerId) {
                    self::db()->update('users', ['status' => 'banned'], 'id = ?', [$ownerId]);
                }
                break;
            case 'warn':
                if ($ownerId) {
                    NotificationService::notifySystem($ownerId, 'report.warned', (string) $report['reason'], $reportId, 'report');
                }
                break;
        }

        $status = $action === 'dismiss' ? 'rejected' : 'resolved';
        $db = self::db();
        $db->update('reports', ['status' => $status], 'id = ?', [$reportId]);
        $db->insert('report_actions', [
            'report_id'  => $reportId,
            'admin_id'   => $adminId,
            'action'     => $action,
            'note'       => mb_substr($note, 0, 255),
            'created_at' => now_utc(),
        ]);
        AdminService::log('report.' . $action, 'report', $reportId, $type . '#' . $targetId);
        return ['ok' => true, 'error' => null, 'status' => $status];
    }
}
