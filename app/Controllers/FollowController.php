<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\FollowService;

/**
 * 关注接口。
 */
class FollowController
{
    public function toggle(Request $req): void
    {
        $uid = AuthService::userId();
        $target = (int) $req->post('user_id', 0);
        if ($target <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
        }
        $following = FollowService::toggle($uid, $target);
        JsonResponse::ok(['user_id' => $target, 'following' => $following], $following ? 'friend.followed' : 'friend.unfollowed');
    }
}
