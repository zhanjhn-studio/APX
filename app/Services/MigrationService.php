<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 数据库迁移：扫描 database/migrations/*.sql，与 schema_migrations 比对后执行未应用文件。
 * 仅执行仓库内随版本发布的 SQL（不做任意路径读取），并逐个文件记录结果。
 */
class MigrationService
{
    public static function dir(): string
    {
        return APP_ROOT . '/database/migrations';
    }

    /** 迁移文件列表（按文件名升序，建议命名 2026_09_16_0001_描述.sql）。 */
    public static function files(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if (substr($f, -4) === '.sql' && is_file($dir . '/' . $f)) {
                $out[] = $f;
            }
        }
        sort($out, SORT_STRING);
        return $out;
    }

    /** 已应用的文件名集合。 */
    public static function applied(): array
    {
        try {
            $rows = Database::instance()->fetchAll('SELECT filename FROM schema_migrations');
        } catch (\Throwable $e) {
            return [];
        }
        $set = [];
        foreach ($rows as $r) {
            $set[(string) $r['filename']] = true;
        }
        return $set;
    }

    /** @return array{pending:string[],applied:string[]} */
    public static function status(): array
    {
        $files = self::files();
        $applied = self::applied();
        $pending = [];
        $done = [];
        foreach ($files as $f) {
            if (isset($applied[$f])) {
                $done[] = $f;
            } else {
                $pending[] = $f;
            }
        }
        return ['pending' => $pending, 'applied' => $done];
    }

    /**
     * 执行全部待应用迁移。
     * @return array{applied:string[],failed:array<string,string>}
     */
    public static function applyPending(): array
    {
        $version = (string) \App\Core\Config::get('app.version', '1.0.0');
        $result = ['applied' => [], 'failed' => []];
        $db = Database::instance();
        foreach (self::status()['pending'] as $file) {
            $path = self::dir() . '/' . $file;
            $sql = @file_get_contents($path);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            try {
                foreach (self::splitStatements($sql) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '' || $stmt === ';') {
                        continue;
                    }
                    $db->pdo()->exec($stmt);
                }
                $db->statement(
                    'INSERT INTO schema_migrations (version, filename, applied_at) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at)',
                    [$version, $file, now_utc()]
                );
                $result['applied'][] = $file;
                AdminService::log('migration.apply', 'migration', null, $file);
            } catch (\Throwable $e) {
                $result['failed'][$file] = $e->getMessage();
                AdminService::log('migration.fail', 'migration', null, $file);
                break; // 失败即停止，避免半应用状态扩散
            }
        }
        return $result;
    }

    /**
     * 字符串感知的分号切分（与 install.php 一致），避免 WAF 正则等字面量内的分号被误切。
     */
    private static function splitStatements(string $sql): array
    {
        $out = [];
        $buf = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($inString) {
                $buf .= $c;
                if ($c === '\\') {
                    if ($i + 1 < $len) {
                        $buf .= $sql[$i + 1];
                        $i++;
                    }
                    continue;
                }
                if ($c === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= $sql[$i + 1];
                        $i++;
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }
            if ($c === "'") {
                $inString = true;
                $buf .= $c;
                continue;
            }
            if ($c === ';') {
                $out[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if ($buf !== '') {
            $out[] = $buf;
        }
        return $out;
    }
}
