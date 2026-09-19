<?php
declare(strict_types=1);
/**
 * APX · 定时清理任务。建议每日通过 cron 调用：
 *   php /path/to/apx/cron/cleanup.php
 * 职责：账号注销冷静期到期清理、软删内容到期、日志保留期、会话与缓存回收。
 */
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;

if (!is_file(APP_ROOT . '/config/database.php')) {
    fwrite(STDERR, "APX 尚未安装，跳过清理。\n");
    exit(1);
}

$db = Database::instance();
$log = Logger::channel('cron');
$now = now_utc();

try {
    // 1) 注销冷静期：超过 30 天未撤销，彻底删除账号
    $deletedUsers = $db->delete(
        'users',
        "status = 'deleting' AND deleted_at IS NOT NULL AND deleted_at < ?",
        [date('Y-m-d H:i:s', strtotime($now . ' -30 days'))]
    );

    // 2) 软删除动态 / 评论：超过 7 天直接物理删除
    $expired = date('Y-m-d H:i:s', strtotime($now . ' -7 days'));
    $db->statement(
        "DELETE FROM posts WHERE deleted_at IS NOT NULL AND deleted_at < ?",
        [$expired]
    );
    $db->statement(
        "DELETE FROM post_comments WHERE deleted_at IS NOT NULL AND deleted_at < ?",
        [$expired]
    );

    // 3) 日志保留期：超过 90 天的访问/安全日志归档删除
    $logExpired = date('Y-m-d H:i:s', strtotime($now . ' -90 days'));
    $db->statement("DELETE FROM waf_logs WHERE created_at < ?", [$logExpired]);
    $db->statement("DELETE FROM login_logs WHERE created_at < ?", [$logExpired]);

    // 4) IP 封禁到期自动解除
    $db->statement("DELETE FROM ip_bans WHERE expires_at IS NOT NULL AND expires_at < ?", [$now]);

    // 5) 登录设备记录：超过 90 天无活动的会话回收
    $sessionsExpired = date('Y-m-d H:i:s', strtotime($now . ' -90 days'));
    $staleSessions = $db->delete('sessions', 'last_active_at < ?', [$sessionsExpired]);

    // 6) 过期的邮箱令牌清理
    $db->statement("DELETE FROM email_tokens WHERE expires_at < ? AND used_at IS NOT NULL", [$now]);

    $log->info('cleanup done', [
        'deleted_users' => $deletedUsers,
        'stale_sessions' => $staleSessions,
        'log_expired_before' => $logExpired,
    ]);
    echo "cleanup ok\n";
} catch (\Throwable $e) {
    $log->error('cleanup failed: ' . $e->getMessage());
    fwrite(STDERR, 'cleanup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
