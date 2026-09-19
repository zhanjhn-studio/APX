<?php
/**
 * APX 实体入口 group.php（路由 /group/{slug}）
 * 仅设置路由后交给前端控制器，复用全部中间件、鉴权与业务逻辑。
 */
$_GET['r'] = (isset($_GET['slug']) && $_GET['slug'] !== '') ? '/group/' . $_GET['slug'] : '/groups';
$__apxFront = is_file(__DIR__ . '/index.php')
    ? __DIR__ . '/index.php'
    : dirname(__DIR__) . '/public/index.php';
require $__apxFront;
