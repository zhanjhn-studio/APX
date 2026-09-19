<?php
/**
 * APX 实体入口 settings.php（路由 /settings）
 * 仅设置路由后交给前端控制器，复用全部中间件、鉴权与业务逻辑。
 */
$_GET['r'] = '/settings';
$__apxFront = is_file(__DIR__ . '/public/index.php')
    ? __DIR__ . '/public/index.php'
    : dirname(__DIR__) . '/public/index.php';
require $__apxFront;
