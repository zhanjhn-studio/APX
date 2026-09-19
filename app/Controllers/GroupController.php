<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\GroupService;

/**
 * 群组控制器：群列表/发现页 + 群详情页（群动态 / 群聊双形态）。
 * 仅取参与组织响应；权限与业务规则全部下沉 GroupService。
 */
class GroupController
{
    public function index(Request $req): void
    {
        $uid = (int) (AuthService::userId() ?? 0);
        $keyword = trim((string) $req->get('q', ''));
        echo View::render('groups/index', [
            'mine'      => GroupService::myGroups($uid),
            'discover'  => GroupService::discover($uid, $keyword, 24),
            'keyword'   => $keyword,
            'css'       => ['css/pages/groups.css'],
        ], 'app');
    }

    public function show(Request $req, string $slug = ''): void
    {
        $uid = (int) (AuthService::userId() ?? 0);
        if ($slug === '') {
            $slug = (string) $req->get('slug', '');
        }
        if ($slug === '') {
            redirect('/groups');
            return;
        }
        $data = GroupService::getBySlug(urldecode($slug), $uid);
        if (!$data) {
            abort(404);
            return;
        }
        // 隐藏群组仅成员可见
        if (($data['group']['visibility'] ?? '') === 'hidden' && !$data['is_member']) {
            abort(404);
            return;
        }
        $groupId = (int) $data['group']['id'];
        echo View::render('group/show', [
            'data'          => $data,
            'posts'         => GroupService::posts($groupId, $uid, 0, 20),
            'members'       => GroupService::members($groupId, 60),
            'announcements' => GroupService::announcements($groupId, 20),
            'requests'      => $data['is_member'] && $data['permissions']['manage'] ? GroupService::requests($groupId, $uid) : [],
            'css'           => ['css/pages/groups.css'],
        ], 'app');
    }

    // ---------------- 生命周期 ----------------

    public function create(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $name = trim((string) $req->post('name', ''));
        if ($name === '' || mb_strlen($name) < 2) {
            JsonResponse::fail(422, 'validation.required', ['name' => __('validation.required')]);
            return;
        }
        $groupId = GroupService::create(
            $uid,
            $name,
            (string) $req->post('description', ''),
            (string) $req->post('visibility', 'public')
        );
        $group = GroupService::byId($groupId);
        JsonResponse::created(['id' => $groupId, 'redirect' => route('/group/' . $group['slug'])], 'group.created');
    }

    public function update(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $ok = GroupService::update($groupId, $uid, [
            'name'        => $req->post('name', null),
            'description' => $req->post('description', null),
            'avatar'      => $req->post('avatar', null),
            'visibility'  => $req->post('visibility', null),
        ]);
        $ok ? JsonResponse::ok([], 'group.updated') : JsonResponse::fail(403, 'permission.denied');
    }

    public function join(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $result = GroupService::join($groupId, $uid, (string) $req->post('message', ''));
        if ($result === 'not_found') {
            JsonResponse::fail(404, 'group.not_found');
            return;
        }
        $msg = $result === 'requested' ? 'group.requested' : 'group.joined';
        JsonResponse::ok(['status' => $result], $msg);
    }

    public function leave(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        if (!GroupService::leave($groupId, $uid)) {
            JsonResponse::fail(403, 'group.owner_cannot_leave');
            return;
        }
        JsonResponse::ok(['redirect' => route('/groups')], 'group.left');
    }

    public function disband(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        if (!GroupService::disband($groupId, $uid)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok(['redirect' => route('/groups')], 'group.disbanded');
    }

    public function transfer(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $target = (int) $req->post('user_id', 0);
        if (!GroupService::transferOwner($groupId, $uid, $target)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok([], 'group.transferred');
    }

    // ---------------- 申请 / 邀请 / 成员 ----------------

    public function respondRequest(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $id = (int) $req->post('request_id', 0);
        $action = $req->post('action', '') === 'accept' ? 'accept' : 'decline';
        if (!GroupService::respondRequest($id, $uid, $action)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok([], $action === 'accept' ? 'group.request_accepted' : 'group.request_declined');
    }

    public function invite(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $targetId = (int) $req->post('user_id', 0);
        if ($targetId <= 0) {
            $username = trim((string) $req->post('username', ''));
            $row = \App\Core\Database::instance()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
            $targetId = $row ? (int) $row['id'] : 0;
        }
        if (!GroupService::invite($groupId, $uid, $targetId)) {
            JsonResponse::fail(422, 'group.invite_failed');
            return;
        }
        JsonResponse::ok([], 'group.invited');
    }

    public function kick(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $targetId = (int) $req->post('user_id', 0);
        if (!GroupService::kick($groupId, $uid, $targetId)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok([], 'group.kicked');
    }

    public function role(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $targetId = (int) $req->post('user_id', 0);
        $role = (string) $req->post('role', 'member');
        if (!GroupService::setRole($groupId, $uid, $targetId, $role)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok([], 'group.role_updated');
    }

    public function mute(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $targetId = (int) $req->post('user_id', 0);
        $minutes = (int) $req->post('minutes', 0);
        if (!GroupService::setMute($groupId, $uid, $targetId, $minutes)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok([], $minutes > 0 ? 'group.muted_done' : 'group.unmuted_done');
    }

    // ---------------- 公告 ----------------

    public function announcement(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $body = (string) $req->post('body', '');
        $pinned = (int) $req->post('pinned', 0) === 1;
        try {
            $id = GroupService::createAnnouncement($groupId, $uid, $body, $pinned);
        } catch (\Throwable $e) {
            $key = $e->getMessage() === 'validation.required' ? 'validation.required' : 'permission.denied';
            JsonResponse::fail($key === 'permission.denied' ? 403 : 422, $key);
            return;
        }
        JsonResponse::created(['id' => $id], 'group.announcement_created');
    }

    public function announcementDelete(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $id = (int) $req->post('id', 0);
        GroupService::deleteAnnouncement($groupId, $uid, $id)
            ? JsonResponse::ok([], 'group.announcement_deleted')
            : JsonResponse::fail(403, 'permission.denied');
    }

    public function announcementPin(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $id = (int) $req->post('id', 0);
        $pinned = (int) $req->post('pinned', 0) === 1;
        GroupService::pinAnnouncement($groupId, $uid, $id, $pinned)
            ? JsonResponse::ok([], 'ok')
            : JsonResponse::fail(403, 'permission.denied');
    }

    // ---------------- 群动态 ----------------

    public function feed(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->get('group_id', 0);
        $before = (int) $req->get('before', 0);
        if (GroupService::roleOf($groupId, $uid) === null) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        $posts = GroupService::posts($groupId, $uid, $before, 20);
        JsonResponse::ok(['items' => $posts, 'has_more' => count($posts) === 20], 'ok');
    }

    public function postCreate(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        try {
            $id = GroupService::createPost($groupId, $uid, (string) $req->post('body', ''));
        } catch (\Throwable $e) {
            $key = $e->getMessage();
            JsonResponse::fail($key === 'group.muted' ? 403 : ($key === 'permission.denied' ? 403 : 422), $key);
            return;
        }
        $post = GroupService::posts($groupId, $uid, 0, 1);
        JsonResponse::created(['id' => $id, 'post' => $post[0] ?? null], 'group.post_created');
    }

    public function postDelete(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $postId = (int) $req->post('post_id', 0);
        GroupService::deletePost($groupId, $uid, $postId)
            ? JsonResponse::ok([], 'group.post_deleted')
            : JsonResponse::fail(403, 'permission.denied');
    }

    public function postLike(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $postId = (int) $req->post('post_id', 0);
        if (GroupService::roleOf($groupId, $uid) === null) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok(GroupService::likePost($groupId, $postId, $uid), 'ok');
    }

    // ---------------- 群聊 ----------------

    public function chat(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->get('group_id', 0);
        $before = (int) $req->get('before', 0);
        if (GroupService::roleOf($groupId, $uid) === null) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        $messages = GroupService::messages($groupId, $uid, $before, 40);
        JsonResponse::ok(['items' => array_reverse($messages), 'has_more' => count($messages) === 40], 'ok');
    }

    public function chatSend(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $replyTo = (int) $req->post('reply_to_id', 0) ?: null;
        try {
            $msg = GroupService::sendMessage($groupId, $uid, (string) $req->post('body', ''), $replyTo, (string) $req->post('type', 'text'));
        } catch (\Throwable $e) {
            $key = $e->getMessage();
            JsonResponse::fail(in_array($key, ['group.muted', 'permission.denied'], true) ? 403 : 422, $key);
            return;
        }
        JsonResponse::created(['message' => $msg], 'ok');
    }

    public function chatRecall(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $groupId = (int) $req->post('group_id', 0);
        $messageId = (int) $req->post('message_id', 0);
        GroupService::recallMessage($groupId, $uid, $messageId)
            ? JsonResponse::ok([], 'messages.recalled')
            : JsonResponse::fail(403, 'permission.denied');
    }
}
