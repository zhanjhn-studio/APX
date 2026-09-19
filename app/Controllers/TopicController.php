<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\PostService;
use App\Services\TopicService;

/**
 * 话题详情页与关注。
 */
class TopicController
{
    public function show(string $slug): void
    {
        $uid = AuthService::userId();
        $topic = TopicService::getBySlug($slug);
        if (!$topic) {
            abort(404, __('topic.not_found'));
            return;
        }
        $posts = PostService::getByTopic($slug, $uid, 0, 12);
        $followed = TopicService::isFollowed((int) $topic['id'], (int) $uid);
        echo View::render('topic/show', [
            'topic'    => $topic,
            'following' => $followed,
            'posts'    => $posts,
            'css'      => ['css/pages/topic.css', 'css/pages/post.css'],
        ], 'app');
    }

    public function posts(Request $req): void
    {
        $uid = (int) (AuthService::userId() ?? 0);
        $slug = (string) $req->get('slug', '');
        $before = (int) $req->get('before', 0);
        $posts = PostService::getByTopic($slug, $uid, $before, 20);
        JsonResponse::ok(['items' => $posts, 'has_more' => count($posts) === 20], 'ok');
    }

    public function follow(Request $req): void
    {
        $uid = AuthService::userId();
        $topic = PostService::getTopicBySlug((string) $req->post('slug', ''));
        if (!$topic) {
            JsonResponse::fail(404, 'topic.not_found');
            return;
        }
        TopicService::follow((int) $topic['id'], $uid);
        JsonResponse::ok(['followed' => true, 'count' => (int) $topic['follower_count'] + 1], 'topic.followed');
    }

    public function unfollow(Request $req): void
    {
        $uid = AuthService::userId();
        $topic = PostService::getTopicBySlug((string) $req->post('slug', ''));
        if (!$topic) {
            JsonResponse::fail(404, 'topic.not_found');
            return;
        }
        TopicService::unfollow((int) $topic['id'], $uid);
        JsonResponse::ok(['followed' => false, 'count' => max(0, (int) $topic['follower_count'] - 1)], 'topic.unfollowed');
    }
}
