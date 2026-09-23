<?php
declare(strict_types=1);
/**
 * APX - 应用基础配置
 * 安装向导会写入 storage/cache 之外的真实值；此处为默认值。
 */
return [
    'name'            => 'APX',
    'slogan'          => '连接真实的社交关系',
    'base_url'        => '',
    // 干净 URL（/login、/home ...）。默认关闭，保证在任意服务器零配置可用（走 index.php?r=...）。
    // 若要开启，必须先把服务器未知路径转发到入口，然后改 true：
    //   Nginx: 在 server{} 内加  location / { try_files $uri $uri/ /index.php?$query_string; }
    //   Apache: 根目录 .htaccess（已随项目提供）
    // 开启后若出现 404，说明服务器规则未生效，改回 false 即恢复。
    'pretty_urls'     => false,
    // 实体文件路由：各页面输出为真实文件地址（/login.php、/home.php ...），
    // 不依赖服务器 rewrite（.php 天然由 PHP 处理），兼容任意 Nginx/Apache。
    // 实体入口文件同时位于项目根与 public/ 下，两种 web 根部署均可用。
    'file_urls'       => true,
    'timezone'        => 'UTC',
    'theme_default'   => 'mono',
    'mode_default'    => 'dark',        // dark | light | auto
    'lang_default'    => 'zh-CN',
    'register_mode'   => 'open',        // open | email | invite
    'maintenance'     => false,
    'version'         => (function (): string {
        $f = __DIR__ . '/../VERSION';
        return is_file($f) ? (trim(@file_get_contents($f)) ?: '1.0.0') : '1.0.0';
    })(),
    'update_repo'     => 'zhanjhn-studio/APX',
    'update_channel'  => 'tags',        // tags | releases
    'update_branch'   => 'main',
    'update_manifest_url' => '',        // 留空则按 update_repo@update_branch/manifest.json 自动拼接
    'storage_dir'     => __DIR__ . '/../storage',
    'uploads_dir'     => __DIR__ . '/../storage/uploads',
    'logs_dir'        => __DIR__ . '/../storage/logs',
    'cache_dir'       => __DIR__ . '/../storage/cache',
    // v2 缓存驱动：redis（需 Predis + Redis 服务）| file（默认零依赖，Redis 不可用时自动回退）
    'cache_driver'    => 'file',
    // v2 实时驱动：ws（WebSocket + Redis）| sse（文件队列回退）
    'realtime_driver' => 'sse',
    // v2 WebSocket 服务（bin/ws-server.php，由 systemd/supervisor 托管）
    'websocket'       => [
        'host' => '0.0.0.0',
        'port' => 8080,
        // 前端 WS 地址；留空则按 location 自动推断（经 Nginx /ws 反代），也可填 ws://host:8080
        'url'  => '',
    ],
    // v2 异步队列（Redis list + blpop worker）
    'queue' => [
        // true 时上传缩略图改由 worker 异步生成（接口返回时 thumb 暂缺，由 worker 稍后补全）
        'thumbnail_async' => false,
    ],
    // v2 搜索
    'search' => [
        'cache_ttl' => 60, // 热点搜索/联想结果缓存秒数（Redis，无 Redis 时自动回退文件缓存）
    ],
];
