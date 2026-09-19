<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\GroupService;
use App\Services\PostService;

/**
 * 发现页：推荐用户、推荐群组、热门动态、热门话题。
 */
class DiscoverController
{
    public function index(Request $req): void
    {
        $uid = (int) (AuthService::userId() ?? 0);
        $recUsers = PostService::getRecommendedUsers($uid, 8);
        $hotTopics = PostService::getHotTopics(14);
        $hotPosts = PostService::getHotPosts($uid, 12);
        $recGroups = array_slice(array_values(array_filter(
            GroupService::discover($uid, '', 12),
            fn($g) => empty($g['is_member'])
        )), 0, 5);
        echo View::render('discover/index', [
            'rec_users'  => $recUsers,
            'rec_groups' => $recGroups,
            'hot_topics' => $hotTopics,
            'hot_posts'  => $hotPosts,
            'css'        => ['css/pages/discover.css', 'css/pages/post.css', 'css/pages/groups.css'],
        ], 'app');
    }

    public function feed(Request $req): void
    {
        $uid = (int) (AuthService::userId() ?? 0);
        $before = (int) $req->get('before', 0);
        $posts = PostService::getHotPostsCursor($uid, $before, 20);
        JsonResponse::ok(['items' => $posts, 'has_more' => count($posts) === 20], 'ok');
    }
}
