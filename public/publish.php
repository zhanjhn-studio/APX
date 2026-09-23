<?php
/**
 * APX 实体入口 publish.php（路由 /publish）
 * 仅设置路由后交给前端控制器，复用全部中间件、鉴权与业务逻辑。
 */
$_GET['r'] = '/publish';
$__apxFront = is_file(__DIR__ . '/index.php')
    ? __DIR__ . '/index.php'
    : dirname(__DIR__) . '/public/index.php';
require $__apxFront;
