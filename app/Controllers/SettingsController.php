<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\DeviceService;
use App\Services\SettingsService;

/**
 * 用户设置中心：外观、账号、安全（两步验证）、设备管理、隐私（屏蔽词/屏蔽用户/黑名单）、
 * 免打扰与账号注销。敏感操作在 Service 层二次校验。
 */
class SettingsController
{
    public function index(): void
    {
        $uid = (int) AuthService::userId();
        echo View::render('settings/index', [
            'user'      => AuthService::user() ?: [],
            'totp'      => SettingsService::totpStatus($uid),
            'devices'   => DeviceService::list($uid),
            'words'     => SettingsService::words($uid),
            'muted'     => SettingsService::mutedUsers($uid),
            'blocked'   => SettingsService::blockedUsers($uid),
            'dnd'       => SettingsService::dnd($uid),
            'deletion'  => SettingsService::deletionState($uid),
            'css'       => ['css/pages/settings.css'],
        ], 'app');
    }

    public function appearance(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $theme = (string) $req->post('theme', '');
        $mode = (string) $req->post('mode', '');
        if (in_array($theme, ['mono','minimal','graphite','graphite_pro','dark','light','blue','purple','pink','green','gold','cyber','mint'], true)) {
            SettingsService::set($uid, 'theme', $theme);
        }
        if (in_array($mode, ['dark','light','auto'], true)) {
            SettingsService::set($uid, 'appearance', $mode);
        }
        JsonResponse::ok([], 'ok');
    }

    // ---------------- 两步验证 ----------------

    public function totpSetup(Request $req): void
    {
        $uid = (int) AuthService::userId();
        JsonResponse::ok(SettingsService::totpSetup($uid), 'settings.totp.ready');
    }

    public function totpEnable(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $code = trim((string) $req->post('code', ''));
        $result = SettingsService::totpEnable($uid, $code);
        if (!$result['ok']) {
            JsonResponse::fail(422, 'auth.login.totp_invalid', ['code' => __('auth.login.totp_invalid')]);
            return;
        }
        JsonResponse::ok(['codes' => $result['codes']], 'settings.totp.enabled');
    }

    public function totpDisable(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $code = trim((string) $req->post('code', ''));
        if (!SettingsService::totpDisable($uid, $code)) {
            JsonResponse::fail(422, 'settings.totp.invalid_code');
            return;
        }
        JsonResponse::ok([], 'settings.totp.disabled');
    }

    public function backupCodes(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $codes = SettingsService::regenerateBackupCodes($uid);
        if (!$codes) {
            JsonResponse::fail(422, 'settings.totp.not_enabled');
            return;
        }
        JsonResponse::ok(['codes' => $codes], 'settings.totp.backup_regenerated');
    }

    // ---------------- 屏蔽词 ----------------

    public function wordAdd(Request $req): void
    {
        $uid = (int) AuthService::userId();
        if (!SettingsService::addWord($uid, (string) $req->post('word', ''))) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::created(['words' => SettingsService::words($uid)], 'settings.word_added');
    }

    public function wordRemove(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $id = (int) $req->post('id', 0);
        $ok = $id > 0
            ? SettingsService::removeWord($uid, $id)
            : SettingsService::removeWordByText($uid, (string) $req->post('word', ''));
        if (!$ok) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::ok([], 'settings.word_removed');
    }

    // ---------------- 屏蔽用户 / 黑名单 ----------------

    public function muteUser(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $target = self::resolveTarget($req);
        if ($target <= 0 || !SettingsService::muteUser($uid, $target)) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::created(['muted' => SettingsService::mutedUsers($uid)], 'settings.user_muted');
    }

    public function unmuteUser(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $target = (int) $req->post('user_id', 0);
        SettingsService::unmuteUser($uid, $target);
        JsonResponse::ok([], 'settings.user_unmuted');
    }

    public function blockUser(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $target = self::resolveTarget($req);
        if ($target <= 0 || !SettingsService::block($uid, $target)) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::created(['blocked' => SettingsService::blockedUsers($uid)], 'settings.user_blocked');
    }

    public function unblockUser(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $target = (int) $req->post('user_id', 0);
        SettingsService::unblock($uid, $target);
        JsonResponse::ok([], 'settings.user_unblocked');
    }

    // ---------------- 免打扰 ----------------

    public function dndSave(Request $req): void
    {
        $uid = (int) AuthService::userId();
        SettingsService::setDnd(
            $uid,
            (int) $req->post('enabled', 0) === 1,
            (string) $req->post('start', '22:00'),
            (string) $req->post('end', '08:00')
        );
        JsonResponse::ok(SettingsService::dnd($uid), 'settings.dnd_saved');
    }

    // ---------------- 设备管理 ----------------

    public function deviceTrust(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $id = (string) $req->post('id', '');
        $trusted = (int) $req->post('trusted', 0) === 1;
        if ($id === '' || !DeviceService::trust($uid, $id, $trusted)) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::ok(['devices' => DeviceService::list($uid)], 'settings.device_updated');
    }

    public function deviceRevoke(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $id = (string) $req->post('id', '');
        if ($id === '' || !DeviceService::revoke($uid, $id)) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        if (!AuthService::user()) {
            JsonResponse::ok(['redirect' => route('/login')], 'settings.device_revoked_self');
            return;
        }
        JsonResponse::ok(['devices' => DeviceService::list($uid)], 'settings.device_revoked');
    }

    public function deviceRevokeOthers(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $count = DeviceService::revokeOthers($uid);
        JsonResponse::ok(['count' => $count, 'devices' => DeviceService::list($uid)], 'settings.devices_revoked');
    }

    // ---------------- 账号注销 ----------------

    public function accountDelete(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $password = (string) $req->post('password', '');
        if (!SettingsService::requestDeletion($uid, $password)) {
            JsonResponse::fail(422, 'settings.delete.password_wrong', ['password' => __('settings.delete.password_wrong')]);
            return;
        }
        DeviceService::removeCurrent();
        AuthService::logout();
        JsonResponse::ok(['redirect' => route('/login')], 'settings.delete.requested');
    }

    public function accountCancelDelete(Request $req): void
    {
        $uid = (int) AuthService::userId();
        SettingsService::cancelDeletion($uid);
        JsonResponse::ok([], 'settings.delete.cancelled');
    }

    private static function resolveTarget(Request $req): int
    {
        $target = (int) $req->post('user_id', 0);
        if ($target > 0) {
            return $target;
        }
        $username = trim((string) $req->post('username', ''));
        if ($username === '') {
            return 0;
        }
        $row = Database::instance()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
        return $row ? (int) $row['id'] : 0;
    }
}
