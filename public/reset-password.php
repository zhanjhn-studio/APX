<?php
/**
 * APX 实体入口 reset-password.php（路由 /reset-password）
 * 仅设置路由后交给前端控制器，复用全部中间件、鉴权与业务逻辑。
 */
$_GET['r'] = (isset($_GET['token']) && $_GET['token'] !== '') ? '/reset-password/' . $_GET['token'] : '/reset-password';
$__apxFront = is_file(__DIR__ . '/public/index.php')
    ? __DIR__ . '/public/index.php'
    : dirname(__DIR__) . '/public/index.php';
require $__apxFront;