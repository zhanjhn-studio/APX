<?php
declare(strict_types=1);
/**
 * APX 前端控制器。Nginx 将所有请求重写到此文件。
 */
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\LocaleMiddleware;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\SecureHeadersMiddleware;
use App\Middleware\ThemeMiddleware;
use App\Middleware\WafMiddleware;

Session::start();
$request = new Request();

SecureHeadersMiddleware::handle($request);
WafMiddleware::handle($request);
LocaleMiddleware::handle($request);
ThemeMiddleware::handle($request);
MaintenanceMiddleware::handle($request);

$router = new Router($request);
$router->load(require APP_ROOT . '/app/routes.php');
$router->dispatch();
