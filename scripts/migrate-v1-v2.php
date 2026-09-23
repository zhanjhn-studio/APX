#!/usr/bin/env php
<?php
/**
 * APX v1 → v2 迁移向导（CLI）
 *
 * 行为：
 *   1. 校验已安装（config/database.php 存在）
 *   2. 自动备份 config/database.php 与 storage/ 到 .apx-backup-<时间戳>/
 *   3. 确保 schema_migrations 表存在（兼容未建立过该表的极早期 v1）
 *   4. 执行 database/migrations/ 下全部待应用迁移（幂等，失败即停）
 *
 * 用法：
 *   php scripts/migrate-v1-v2.php
 *   php scripts/migrate-v1-v2.php --yes         # 跳过确认
 *   php scripts/migrate-v1-v2.php --no-backup   # 跳过备份
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "本脚本只能在命令行运行\n");
    exit(1);
}

$skipConfirm = in_array('--yes', $argv, true);
$noBackup    = in_array('--no-backup', $argv, true);

require_once __DIR__ . '/../app/bootstrap.php';

$dbFile = APP_ROOT . '/config/database.php';
if (!is_file($dbFile)) {
    fwrite(STDERR, "✗ 未检测到 config/database.php，请先安装 APX v1。\n");
    exit(1);
}

echo "=== APX v1 → v2 迁移向导 ===\n";

// 确保迁移记录表存在（兼容未建立过该表的极早期 v1）
$db = \App\Core\Database::instance();
$db->statement(
    "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `version` VARCHAR(32) NOT NULL,
        `filename` VARCHAR(128) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_migration` (`version`,`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$status  = \App\Services\MigrationService::status();
$pending = $status['pending'];
$applied = $status['applied'];

echo "• 已应用迁移: " . count($applied) . " 个\n";
if (empty($pending)) {
    echo "✓ 没有待应用的迁移，数据库已是最新。\n";
    exit(0);
}
echo "• 待应用迁移 (" . count($pending) . " 个):\n";
foreach ($pending as $f) {
    echo "    - $f\n";
}

if (!$skipConfirm) {
    echo "是否执行以上迁移（存量数据将完整保留）？[yes/no]: ";
    $ans = trim(fgets(STDIN));
    if (strtolower($ans) !== 'yes' && $ans !== 'y') {
        echo "已取消。\n";
        exit(0);
    }
}

// 备份（迁移前自动快照，便于回滚）
if (!$noBackup) {
    $backupDir = APP_ROOT . '/.apx-backup-' . gmdate('Ymd-His');
    echo "• 备份 config/database.php 与 storage/ → $backupDir\n";
    mkdir($backupDir, 0755, true);
    copy($dbFile, $backupDir . '/database.php');
    $src = APP_ROOT . '/storage';
    $dst = $backupDir . '/storage';
    if (is_dir($src)) {
        mkdir($dst, 0755, true);
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $rel = substr((string) $item->getRealPath(), strlen($src));
            $target = $dst . '/' . ltrim($rel, '/\\');
            if ($item->isDir()) {
                mkdir($target, 0755, true);
            } elseif ($item->isFile()) {
                copy($item->getRealPath(), $target);
            }
        }
    }
}

echo "• 执行迁移...\n";
$result = \App\Services\MigrationService::applyPending();
foreach ($result['applied'] as $f) {
    echo "  ✓ $f\n";
}
if (!empty($result['failed'])) {
    echo "✗ 以下迁移失败：\n";
    foreach ($result['failed'] as $f => $msg) {
        echo "    $f: $msg\n";
    }
    echo "请修复后重新运行本脚本（已成功的迁移不会重复执行）。\n";
    exit(1);
}
echo "✅ 迁移完成！APX 已升级到 v2（结构增量迁移，存量数据完整保留）。\n";
echo "   下一步：运行 composer install 启用 Redis/WebSocket/队列（可选，缺省自动回退）。\n";
exit(0);
