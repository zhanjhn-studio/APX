<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\NotificationService;

class NotificationsController
{
    public function index(): void
    {
        $uid = AuthService::userId();
        $list = NotificationService::list($uid, 40);
        NotificationService::markAllRead($uid);
        echo View::render('notifications/index', [
            'notifications' => $list,
            'css' => ['css/pages/notifications.css'],
        ], 'app');
    }

    public function readAll(Request $req): void
    {
        NotificationService::markAllRead(AuthService::userId());
        JsonResponse::ok([], 'ok');
    }
}
