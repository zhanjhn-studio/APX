<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\RelationService;

class FriendsController
{
    public function index(): void
    {
        $uid = AuthService::userId();
        echo View::render('friends/index', [
            'friends'   => RelationService::listFriends($uid),
            'special'   => RelationService::listFriends($uid, true),
            'incoming'  => RelationService::incomingRequests($uid),
            'outgoing'  => RelationService::outgoingRequests($uid),
            'followers' => RelationService::followers($uid),
            'following' => RelationService::following($uid),
            'css'       => ['css/pages/friends.css'],
        ], 'app');
    }

    public function send(Request $req): void
    {
        $uid = AuthService::userId();
        $target = (int) $req->post('user_id', 0);
        $msg = trim((string) $req->post('message', ''));
        if ($target <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
        }
        $status = RelationService::sendRequest($uid, $target, $msg);
        JsonResponse::ok(['status' => $status], 'ok');
    }

    public function respond(Request $req): void
    {
        $uid = AuthService::userId();
        $id = (int) $req->post('request_id', 0);
        $action = $req->post('action', '') === 'accept' ? 'accept' : 'decline';
        $status = RelationService::respond($id, $uid, $action);
        JsonResponse::ok(['status' => $status], 'ok');
    }

    public function special(Request $req): void
    {
        $uid = AuthService::userId();
        $friend = (int) $req->post('user_id', 0);
        $on = (int) $req->post('on', 0);
        RelationService::setSpecial($uid, $friend, $on === 1);
        JsonResponse::ok([], 'ok');
    }

    public function remove(Request $req): void
    {
        $uid = AuthService::userId();
        $friend = (int) $req->post('user_id', 0);
        RelationService::removeFriend($uid, $friend);
        JsonResponse::ok([], 'ok');
    }
}
