<?php
declare(strict_types=1);
/**
 * APX v2 - Redis 连接配置（示例）。
 * 复制为 config/redis.php 并填入真实值；不创建 redis.php 时 Redis 相关功能自动回退到文件实现。
 */
return [
    'scheme'   => 'tcp',
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'password' => '',
    'db'       => 0,
];
