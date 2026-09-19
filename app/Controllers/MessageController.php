<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\MessageService;

/**
 * 私聊 / 群聊：会话列表、消息拉取（含上下文跳转）、发送（文本/表情/图片/文件/位置、引用回复）、
 * 撤回、表情回应、已读回执、置顶、免打扰、删除会话、会话内搜索与「正在输入」。
 */
class MessageController
{
    public function index(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $openId = (int) $req->get('c', 0);
        // 支持 /messages?start=用户名 直接打开与某用户的会话（个人主页「发消息」入口）
        if ($openId <= 0) {
            $start = (string) $req->get('start', '');
            if ($start !== '') {
                $peer = Database::instance()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$start]);
                if ($peer) {
                    $openId = MessageService::getOrCreateDirect($uid, (int) $peer['id']);
                }
            }
        }
        echo View::render('messages/index', [
            'conversations' => MessageService::conversations($uid),
            'open_id' => $openId,
            'uid' => $uid,
            'css' => ['css/pages/messages.css'],
        ], 'app');
    }

    /** 拉取会话消息（GET /api/messages?c=&before= | &around=），并标记为已读。 */
    public function view(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->get('c', 0);
        $before = (int) $req->get('before', 0);
        $around = (int) $req->get('around', 0);
        if (!MessageService::isMember($c, $uid)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        if ($around > 0) {
            $msgs = MessageService::messagesAround($c, $uid, $around);
            $more = false;
        } else {
            $msgs = MessageService::messages($c, $uid, $before, 40);
            $more = count($msgs) === 40;
            $msgs = array_reverse($msgs);
        }
        MessageService::markRead($c, $uid);
        JsonResponse::ok([
            'messages'        => $msgs,
            'has_more'        => $more,
            'conversation_id' => $c,
            'typing'          => MessageService::typingNames($c, $uid),
            'anchor'          => $around,
        ], 'ok');
    }

    public function send(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->post('conversation_id', 0);
        $type = (string) $req->post('type', 'text');
        $body = (string) $req->post('body', '');
        if (!MessageService::isMember($c, $uid)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        if (!in_array($type, ['text', 'emoji', 'image', 'file', 'location'], true)) {
            $type = 'text';
        }

        if ($type === 'text') {
            $body = trim($body);
            if ($body === '') {
                JsonResponse::fail(422, 'validation.required');
                return;
            }
            $body = mb_substr($body, 0, 4000);
        } elseif ($type === 'emoji') {
            $body = mb_substr(trim($body), 0, 8);
            if ($body === '') {
                JsonResponse::fail(422, 'validation.required');
                return;
            }
        } else {
            // 图片 / 文件 / 位置：body 为 JSON 元数据，必须可解析
            $meta = json_decode($body, true);
            if (!is_array($meta) || empty($meta)) {
                JsonResponse::fail(422, 'validation.invalid');
                return;
            }
            if ($type === 'location' && (!isset($meta['lat'], $meta['lng']))) {
                JsonResponse::fail(422, 'validation.invalid');
                return;
            }
            if ($type !== 'location' && empty($meta['path'])) {
                JsonResponse::fail(422, 'validation.invalid');
                return;
            }
            $body = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        $burn = (string) $req->post('burn_mode', 'none');
        $burnValue = (int) $req->post('burn_value', 0);
        if (!in_array($burn, ['none', 'after_view', 'after_time'], true)) {
            $burn = 'none';
        }
        $replyTo = (int) $req->post('reply_to_id', 0) ?: null;
        if ($replyTo !== null) {
            $exists = Database::instance()->fetch(
                'SELECT id FROM messages WHERE id = ? AND conversation_id = ? LIMIT 1',
                [$replyTo, $c]
            );
            if (!$exists) {
                $replyTo = null;
            }
        }

        $msg = MessageService::send($c, $uid, $type, $body, $replyTo, $burn, $burnValue);
        JsonResponse::created(['message' => $msg], 'ok');
    }

    public function recall(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $id = (int) $req->post('message_id', 0);
        $ok = MessageService::recall($id, $uid);
        JsonResponse::ok(['recalled' => $ok], $ok ? 'ok' : 'auth.login.expired');
    }

    public function react(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $id = (int) $req->post('message_id', 0);
        $emoji = trim((string) $req->post('emoji', '👍'));
        $r = MessageService::toggleReaction($id, $uid, mb_substr($emoji, 0, 8));
        JsonResponse::ok($r, 'ok');
    }

    public function receipts(Request $req): void
    {
        $id = (int) $req->get('message_id', 0);
        JsonResponse::ok([
            'receipts' => MessageService::readReceipts($id),
            'count'    => MessageService::readCount($id),
        ], 'ok');
    }

    public function markRead(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->post('conversation_id', 0);
        MessageService::markRead($c, $uid);
        JsonResponse::ok([], 'ok');
    }

    public function start(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $target = (int) $req->post('user_id', 0);
        if ($target <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        $cid = MessageService::getOrCreateDirect($uid, $target);
        JsonResponse::ok(['conversation_id' => $cid, 'redirect' => route('/messages', ['c' => $cid])], 'ok');
    }

    /** 会话内消息搜索。 */
    public function search(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->get('c', 0);
        $q = trim((string) $req->get('q', ''));
        if (!MessageService::isMember($c, $uid)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        $items = MessageService::searchMessages($c, $uid, $q, 30);
        JsonResponse::ok(['items' => $items, 'count' => count($items), 'q' => $q], 'ok');
    }

    /** 置顶 / 免打扰 / 删除会话。 */
    public function pin(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->post('conversation_id', 0);
        $on = (int) $req->post('on', 0) === 1;
        if (!MessageService::setPinned($c, $uid, $on)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok(['on' => $on], $on ? 'messages.pinned_done' : 'messages.unpinned_done');
    }

    public function mute(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->post('conversation_id', 0);
        $on = (int) $req->post('on', 0) === 1;
        if (!MessageService::setMuted($c, $uid, $on)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok(['on' => $on], $on ? 'messages.muted_done' : 'messages.unmuted_done');
    }

    public function deleteConv(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->post('conversation_id', 0);
        if (!MessageService::deleteConversation($c, $uid)) {
            JsonResponse::fail(403, 'permission.denied');
            return;
        }
        JsonResponse::ok(['redirect' => route('/messages')], 'messages.deleted');
    }

    /** 正在输入心跳（节流由前端控制）。 */
    public function typing(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $c = (int) $req->post('conversation_id', 0);
        MessageService::setTyping($c, $uid);
        JsonResponse::ok([], 'ok');
    }
}
