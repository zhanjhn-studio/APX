<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\View;
use App\Services\AuthService;
use App\Services\PostService;

/**
 * 首页：关系驱动的信息流 + 右侧推荐与通知入口。
 */
class HomeController
{
    public function index(): void
    {
        $user = AuthService::user();
        $uid = (int) ($user['id'] ?? 0);
        $posts = PostService::getFeed($uid, 0, 20);
        $recommended = PostService::getRecommendedUsers($uid, 5);
        $notifications = PostService::getUnreadNotifications($uid, 6);
        $unread = PostService::unreadCount($uid);

        echo View::render('home/index', [
            'user'         => $user,
            'posts'        => $posts,
            'recommended'  => $recommended,
            'notifications' => $notifications,
            'unread'       => $unread,
            'css'          => ['css/pages/home.css', 'css/pages/post.css'],
        ], 'app');
    }

    /**
     * 独立发布页：富文本撰写 + 可见性 / 选项 / 收藏，复用统一图标与黑白设计令牌。
     */
    public function compose(): void
    {
        $user = AuthService::user() ?: [];
        echo View::render('publish/index', [
            'user' => $user,
            'css'  => ['css/pages/publish.css'],
        ], 'app');
    }
}
