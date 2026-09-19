<?php
declare(strict_types=1);
/**
 * 更新服务：支持两种版本源
 *  - channel = tags      ：读取 GitHub 仓库 tag（api.github.com/repos/{repo}/tags），取最高 semver 与本地比较
 *  - channel = manifest  ：读取仓库根 manifest.json（由 scripts/release.sh 生成）
 *  - channel = releases  ：与 tags 相同实现，额外抓取 latest release 的说明/时间
 * 仅负责「检查与展示」；下载与覆盖由管理员执行（见 admin.update.apply_hint）。
 */
namespace App\Services;

use App\Core\Config;

final class UpdateService
{
    public static function currentVersion(): string
    {
        return (string) Config::get('app.version', '1.0.0');
    }

    public static function channel(): string
    {
        $c = (string) Config::get('app.update_channel', 'tags');
        return in_array($c, ['tags', 'releases', 'manifest'], true) ? $c : 'tags';
    }

    public static function repo(): string
    {
        return (string) Config::get('app.update_repo', '');
    }

    public static function manifestUrl(): string
    {
        $cfg = Config::get('app.update_manifest_url', '');
        if (is_string($cfg) && $cfg !== '') {
            return $cfg;
        }
        $repo = self::repo();
        if ($repo === '') {
            return '';
        }
        $branch = (string) Config::get('app.update_branch', 'main');
        return "https://raw.githubusercontent.com/{$repo}/{$branch}/manifest.json";
    }

    /**
     * @return array{current:string,latest:?string,has_update:bool,notes:string,url:string,download:string,published_at:string,channel:string,error:string}
     */
    public static function check(): array
    {
        $out = [
            'current'      => self::currentVersion(),
            'latest'       => null,
            'has_update'   => false,
            'notes'        => '',
            'url'          => '',
            'download'     => '',
            'published_at' => '',
            'channel'      => self::channel(),
            'error'        => '',
        ];

        if ($out['channel'] === 'manifest') {
            return self::checkManifest($out);
        }

        $repo = self::repo();
        if ($repo === '') {
            $out['error'] = 'update.not_configured';
            return $out;
        }

        // 1) 拉取 tags 列表，取最高 semver
        $json = self::httpGet("https://api.github.com/repos/{$repo}/tags?per_page=100");
        $list = $json !== null ? json_decode($json, true) : null;
        if (!is_array($list)) {
            $out['error'] = 'update.fetch_failed';
            return $out;
        }
        $bestName = null;
        $bestVer  = null;
        foreach ($list as $t) {
            $name = (string) ($t['name'] ?? '');
            $ver  = ltrim($name, 'vV');
            if ($name === '' || !preg_match('/^\d+(\.\d+){0,3}$/', $ver)) {
                continue;
            }
            if ($bestVer === null || version_compare($ver, $bestVer, '>')) {
                $bestVer  = $ver;
                $bestName = $name;
            }
        }
        if ($bestVer === null || $bestName === null) {
            $out['error'] = 'update.bad_manifest';
            return $out;
        }

        $out['latest']     = $bestVer;
        $out['has_update'] = version_compare($bestVer, $out['current'], '>');
        $out['url']        = "https://github.com/{$repo}/releases/tag/{$bestName}";
        $out['download']   = "https://github.com/{$repo}/archive/refs/tags/{$bestName}.zip";

        // 2) 补充 latest release 的说明 / 发布时间 / 资源直链
        $rel = self::httpGet("https://api.github.com/repos/{$repo}/releases/latest");
        $rd  = $rel !== null ? json_decode($rel, true) : null;
        if (is_array($rd)) {
            if (!empty($rd['body'])) {
                $out['notes'] = (string) $rd['body'];
            }
            if (!empty($rd['published_at'])) {
                $out['published_at'] = (string) $rd['published_at'];
            }
            if (!empty($rd['html_url'])) {
                $out['url'] = (string) $rd['html_url'];
            }
            if (!empty($rd['zipball_url'])) {
                $out['download'] = (string) $rd['zipball_url'];
            }
        }
        return $out;
    }

    private static function checkManifest(array $out): array
    {
        $url = self::manifestUrl();
        if ($url === '') {
            $out['error'] = 'update.not_configured';
            return $out;
        }
        $json = self::httpGet($url);
        if ($json === null) {
            $out['error'] = 'update.fetch_failed';
            return $out;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['version'])) {
            $out['error'] = 'update.bad_manifest';
            return $out;
        }
        $repo = self::repo();
        $out['latest']       = (string) $data['version'];
        $out['notes']        = (string) ($data['notes'] ?? '');
        $out['published_at'] = (string) ($data['released_at'] ?? '');
        $out['download']     = (string) ($data['download'] ?? '');
        $out['url']          = (string) ($data['download'] ?? ($repo !== '' ? "https://github.com/{$repo}/releases" : ''));
        $out['has_update']   = version_compare((string) $data['version'], $out['current'], '>');
        return $out;
    }

    /** 本地已有的备份目录（scripts/update.sh 生成 .apx-backup-<时间戳>）。 */
    public static function backups(int $limit = 10): array
    {
        $out = [];
        foreach (glob(APP_ROOT . '/.apx-backup-*', GLOB_ONLYDIR) ?: [] as $dir) {
            $out[] = [
                'name'  => basename($dir),
                'path'  => $dir,
                'time'  => date('Y-m-d H:i:s', (int) @filemtime($dir)),
                'size'  => self::dirSize($dir),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
        return array_slice($out, 0, $limit);
    }

    private static function dirSize(string $dir): int
    {
        $size = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += (int) $file->getSize();
            }
        }
        return $size;
    }

    /**
     * 下载更新包到 storage/cache/updates/（仅落盘，不自动覆盖，避免破坏运行中的站点）。
     * @return array{ok:bool,path:string,bytes:int,error:string}
     */
    public static function download(string $url): array
    {
        $out = ['ok' => false, 'path' => '', 'bytes' => 0, 'error' => ''];
        if (!preg_match('#^https://#i', $url)) {
            $out['error'] = 'update.bad_url';
            return $out;
        }
        $dir = rtrim((string) \App\Core\Config::get('app.cache_dir'), '/') . '/updates';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $out['error'] = 'update.dir_failed';
            return $out;
        }
        $target = $dir . '/apx-' . date('Ymd-His') . '.zip';
        $bytes = 0;
        if (function_exists('curl_init')) {
            $fp = fopen($target, 'wb');
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_USERAGENT      => 'APX-Updater',
            ]);
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            if ($ok === false || $code < 200 || $code >= 300) {
                @unlink($target);
                $out['error'] = 'update.fetch_failed';
                return $out;
            }
            $bytes = (int) @filesize($target);
        } else {
            $body = self::httpGet($url);
            if ($body === null) {
                $out['error'] = 'update.fetch_failed';
                return $out;
            }
            $bytes = (int) @file_put_contents($target, $body);
        }
        if ($bytes <= 0) {
            @unlink($target);
            $out['error'] = 'update.fetch_failed';
            return $out;
        }
        $out['ok'] = true;
        $out['path'] = str_replace('\\', '/', $target);
        $out['bytes'] = $bytes;
        return $out;
    }

    private static function httpGet(string $url): ?string
    {
        $headers = [
            'User-Agent: APX-Updater',
            'Accept: application/vnd.github+json',
        ];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout'    => 8,
                'header'     => implode("\r\n", $headers),
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            return $body === false ? null : $body;
        }
        return null;
    }
}
