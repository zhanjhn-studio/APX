<?php
declare(strict_types=1);
/**
 * APX - 安全配置
 * WAF 默认观察模式上线，避免误伤正常提交。
 */
return [
    'waf' => [
        'mode' => 'observe',                 // observe（只记录）| defense（真正拦截）
        'thresholds' => [
            'log'        => 1,
            'challenge'  => 5,
            'block'      => 10,
            'ban'        => 20,
        ],
        'ban_tiers' => [300, 3600, 86400],   // 阶梯封禁秒数：5min → 1h → 24h
    ],
    'csrf' => [
        'token_name' => 'csrf_token',
        'header_name' => 'X-CSRF-Token',
        'ttl' => 7200,
    ],
    'session' => [
        'name' => 'apx_session',
        'lifetime' => 1209600,              // 14 天
        'regenerate_probability' => 10,     // 每次请求 10% 概率重生成
        'cookie_secure' => false,           // 安装时按是否 HTTPS 自动置 true
        'cookie_samesite' => 'Lax',
    ],
    'login' => [
        'max_attempts' => 5,
        'lockout_seconds' => 900,
        'remember_ttl' => 2592000,          // 30 天
    ],
    'rate_limit' => [
        'default' => ['requests' => 120, 'window' => 60],
        'login'   => ['requests' => 10,  'window' => 60],
        'api'     => ['requests' => 60,  'window' => 60],
    ],
];
