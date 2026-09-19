<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\PostService;
use App\Services\FavoriteService;

/**
 * 动态接口与页面。写操作返回统一 JSON；详情页服务端渲染首屏，交互由 post-detail.js 接管。
 */
class PostController
{
    public function feed(Request $req): void
    {
        $uid = AuthService::userId();
        $before = (int) $req->get('before', 0);
        $posts = PostService::getFeed($uid, $before, 20);
        JsonResponse::ok(['posts' => $posts, 'has_more' => count($posts) === 20], 'ok');
    }

    public function store(Request $req): void
    {
        $uid = AuthService::userId();
        $body = trim((string) $req->post('body', ''));
        $visibility = (string) $req->post('visibility', 'public');
        $mediaRaw = $req->post('media', '[]');
        $media = is_array($mediaRaw) ? $mediaRaw : json_decode($mediaRaw ?: '[]', true);
        if (!is_array($media)) {
            $media = [];
        }
        if ($body === '' && empty($media)) {
            JsonResponse::fail(422, 'validation.required', ['body' => __('validation.required')]);
            return;
        }
        if (mb_strlen($body) > 2000) {
            JsonResponse::fail(422, 'validation.max', ['body' => __('validation.max')]);
            return;
        }
        $id = PostService::createPost($uid, $body, $visibility, $media);
        JsonResponse::created(['id' => $id, 'redirect' => '/post/' . $id], 'post.created');
    }

    public function show(string $id): void
    {
        $uid = AuthService::userId();
        $post = PostService::getPost((int) $id, $uid);
        if (!$post) {
            abort(404, __('post.not_found'));
            return;
        }
        echo View::render('post/show', ['post' => $post, 'css' => ['css/pages/post.css']], 'app');
    }

    public function update(Request $req): void
    {
        $uid = AuthService::userId();
        $id = (int) $req->post('post_id', 0);
        $body = trim((string) $req->post('body', ''));
        $visibility = (string) $req->post('visibility', 'public');
        if ($id <= 0 || $body === '') {
            JsonResponse::fail(422, 'validation.required');
            return;
        }
        if (!PostService::updatePost($id, $uid, $body, $visibility)) {
            JsonResponse::fail(403, 'post.cannot_edit');
            return;
        }
        JsonResponse::ok([], 'post.updated');
    }

    public function delete(Request $req): void
    {
        $uid = AuthService::userId();
        $id = (int) $req->post('post_id', 0);
        if (!PostService::deletePost($id, $uid)) {
            JsonResponse::fail(403, 'post.cannot_delete');
            return;
        }
        JsonResponse::ok([], 'post.deleted');
    }

    public function like(Request $req): void
    {
        $uid = AuthService::userId();
        $postId = (int) $req->post('post_id', 0);
        if ($postId <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        $r = PostService::toggleLike($postId, $uid);
        JsonResponse::ok(['post_id' => $postId, 'liked' => $r['liked'], 'count' => $r['count']], 'ok');
    }

    public function comment(Request $req): void
    {
        $uid = AuthService::userId();
        $postId = (int) $req->post('post_id', 0);
        $body = trim((string) $req->post('body', ''));
        if ($postId <= 0 || $body === '') {
            JsonResponse::fail(422, 'validation.required');
            return;
        }
        $parent = $req->post('parent_id') !== null ? (int) $req->post('parent_id') : null;
        $replyTo = $req->post('reply_to') !== null ? (int) $req->post('reply_to') : null;
        try {
            $comment = PostService::addComment($postId, $uid, $body, $parent, $replyTo);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::fail(404, 'post.not_found');
            return;
        }
        JsonResponse::created(['comment' => $comment], 'post.commented');
    }

    public function comments(Request $req): void
    {
        $uid = AuthService::userId();
        $postId = (int) $req->get('post_id', 0);
        if ($postId <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::ok(['comments' => PostService::getCommentsTree($postId, $uid)], 'ok');
    }

    public function commentLike(Request $req): void
    {
        $uid = AuthService::userId();
        $cid = (int) $req->post('comment_id', 0);
        if ($cid <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        $r = PostService::toggleCommentLike($cid, $uid);
        JsonResponse::ok(['comment_id' => $cid, 'liked' => $r['liked'], 'count' => $r['count']], 'ok');
    }

    public function commentDelete(Request $req): void
    {
        $uid = AuthService::userId();
        $cid = (int) $req->post('comment_id', 0);
        if (!PostService::deleteComment($cid, $uid)) {
            JsonResponse::fail(403, 'post.cannot_delete');
            return;
        }
        JsonResponse::ok([], 'post.comment_deleted');
    }

    public function favorite(Request $req): void
    {
        $uid = AuthService::userId();
        $postId = (int) $req->post('post_id', 0);
        $folderId = $req->post('folder_id') !== null && $req->post('folder_id') !== '' ? (int) $req->post('folder_id') : null;
        if ($postId <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        $fav = FavoriteService::toggle($uid, $postId, $folderId);
        $count = (int) \App\Core\Database::instance()->column('SELECT favorite_count FROM posts WHERE id = ?', [$postId]);
        JsonResponse::ok(['post_id' => $postId, 'favorited' => $fav, 'count' => $count], 'ok');
    }

    public function share(Request $req): void
    {
        $uid = AuthService::userId();
        $postId = (int) $req->post('post_id', 0);
        if ($postId <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        try {
            $newId = PostService::repost($postId, $uid);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::fail(404, 'post.not_found');
            return;
        }
        $count = (int) \App\Core\Database::instance()->column('SELECT share_count FROM posts WHERE id = ?', [$postId]);
        JsonResponse::ok(['post_id' => $postId, 'new_post_id' => $newId, 'count' => $count, 'redirect' => '/post/' . $newId], 'post.shared');
    }
}
