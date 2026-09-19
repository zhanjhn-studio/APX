<?php
declare(strict_types=1);
/**
 * APX - 数据库配置（示例）
 * 安装向导会自动生成 database.php（不提交进版本库）。
 */
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'apx',
    'username' => 'apx',
    'password' => '',
    'charset'  => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'  => [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES  => false,
        PDO::ATTR_PERSISTENT        => false,
    ],
];
