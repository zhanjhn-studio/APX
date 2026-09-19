<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\PostService;
use App\Services\ProfileService;

/**
 * 个人主页：资料、计数、关系与动态列表（无限滚动）。
 */
class ProfileController
{
    public function show(Request $req, string $username = ''): void
    {
        $uid = (int) (AuthService::userId() ?? 0);
        if ($username === '') {
            $me = AuthService::user();
            if (!$me) {
                abort(404);
                return;
            }
            $username = (string) $me['username'];
        }
        $data = ProfileService::getByUsername(urldecode($username), $uid);
        if (!$data) {
            abort(404);
            return;
        }
        echo View::render('profile/show', [
            'data' => $data,
            'css'  => ['css/pages/profile.css'],
        ], 'app');
    }

    public function posts(Request $req): void
    {
        $username = (string) $req->get('username', '');
        $before = (int) $req->get('before', 0);
        $uid = (int) (AuthService::userId() ?? 0);
        $user = Database::instance()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [urldecode($username)]);
        if (!$user) {
            JsonResponse::ok(['items' => [], 'has_more' => false], 'ok');
            return;
        }
        $posts = PostService::getUserPosts((int) $user['id'], $uid, $before, 20);
        JsonResponse::ok(['items' => $posts, 'has_more' => count($posts) === 20], 'ok');
    }
}
