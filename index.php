<?php
declare(strict_types=1);
/**
 * APX - 顶层入口（项目根部署时使用）
 * 当站点根目录指向项目根（而非 public/）时，本文件作为前端控制器入口，转发到
 * public/ 下的真实引导。静态资源请在 Web 服务器中将 /assets 指向 public/assets
 * （详见 nginx.conf.example）。传统安全部署（root 指向 public/）不会经过本文件。
 */
require_once __DIR__ . '/public/index.php';
