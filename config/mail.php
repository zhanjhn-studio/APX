<?php
declare(strict_types=1);
/**
 * APX - 邮件配置
 * dev_mode = true 时（未配置 SMTP）邮件写入日志表而非真实外发。
 */
return [
    'driver'       => 'smtp',               // smtp | log
    'host'         => '',
    'port'         => 465,
    'encryption'   => 'ssl',                // ssl | tls
    'username'     => '',
    'password'     => '',
    'from_address' => '',
    'from_name'    => 'APX',
    'dev_mode'     => true,                 // 安装/未配置时自动降级
];
