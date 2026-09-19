<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Core\WafService;
use App\Services\AdminService;
use App\Services\MigrationService;
use App\Services\ReportService;
use App\Services\SiteSettingsService;
use App\Services\UpdateService;

/**
 * 管理后台：概览、用户、内容、群组、举报、角色权限、站点设置、主题语言、统计、日志、WAF、更新。
 * 路由层已通过 auth + admin + permission 三道校验；本层只取参与组织响应。
 */
class AdminController
{
    // ==================================================================
    // 概览 / 统计
    // ==================================================================

    public function index(): void
    {
        echo View::render('admin/index', [
            'stats'   => AdminService::overview(),
            'reports' => ReportService::counts(),
            'trend'   => AdminService::trend(14),
            'seg'     => 'index',
        ], 'admin');
    }

    public function stats(): void
    {
        echo View::render('admin/stats', [
            'stats'  => AdminService::overview(),
            'trend'  => AdminService::trend(30),
            'boards' => AdminService::leaderboards(),
            'reports'=> ReportService::counts(),
            'seg'    => 'stats',
        ], 'admin');
    }

    // ==================================================================
    // 用户管理
    // ==================================================================

    public function users(Request $req): void
    {
        $keyword = trim((string) $req->get('q', ''));
        $status = (string) $req->get('status', '');
        $page = max(1, (int) $req->get('page', 1));
        $uid = (int) ($req->get('uid', 0));
        echo View::render('admin/users', [
            'list'    => AdminService::users($keyword, $status, $page),
            'keyword' => $keyword,
            'status'  => $status,
            'roles'   => AdminService::roles(),
            'detail'  => $uid > 0 ? $this->userDetail($uid) : null,
            'seg'     => 'users',
        ], 'admin');
    }

    private function userDetail(int $userId): ?array
    {
        $db = Database::instance();
        $user = $db->fetch(
            'SELECT u.*, p.avatar, p.bio FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ? LIMIT 1',
            [$userId]
        );
        if (!$user) {
            return null;
        }
        unset($user['password_hash']);
        return [
            'user'  => $user,
            'roles' => AdminService::userRoles($userId),
            'stats' => [
                'posts'    => (int) $db->column('SELECT COUNT(*) FROM posts WHERE user_id = ? AND deleted_at IS NULL', [$userId]),
                'friends'  => (int) $db->column('SELECT COUNT(*) FROM friendships WHERE user_id = ?', [$userId]),
                'messages' => (int) $db->column('SELECT COUNT(*) FROM messages WHERE sender_id = ?', [$userId]),
            ],
        ];
    }

    public function userAction(Request $req): void
    {
        $uid = (int) $req->post('user_id', 0);
        $action = (string) $req->post('action', '');
        $ok = false;
        switch ($action) {
            case 'ban':      $ok = AdminService::setUserStatus($uid, 'banned'); break;
            case 'activate': $ok = AdminService::setUserStatus($uid, 'active'); break;
            case 'pending':  $ok = AdminService::setUserStatus($uid, 'pending'); break;
            case 'grant_super':  $ok = AdminService::setSuperAdmin($uid, true); break;
            case 'revoke_super': $ok = AdminService::setSuperAdmin($uid, false); break;
            case 'role_grant':   $ok = AdminService::assignRole($uid, (int) $req->post('role_id', 0)); break;
            case 'role_revoke':  $ok = AdminService::revokeRole($uid, (int) $req->post('role_id', 0)); break;
        }
        if (!$ok) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        JsonResponse::ok([], 'admin.user.updated');
    }

    // ==================================================================
    // 内容管理
    // ==================================================================

    public function posts(Request $req): void
    {
        $keyword = trim((string) $req->get('q', ''));
        $state = (string) $req->get('state', '');
        $page = max(1, (int) $req->get('page', 1));
        echo View::render('admin/posts', [
            'list'     => AdminService::posts($keyword, $state, $page),
            'comments' => AdminService::comments($keyword, max(1, (int) $req->get('cpage', 1))),
            'keyword'  => $keyword,
            'state'    => $state,
            'seg'      => 'posts',
        ], 'admin');
    }

    public function postAction(Request $req): void
    {
        $id = (int) $req->post('post_id', 0);
        $action = (string) $req->post('action', '');
        $ok = $action === 'remove' ? AdminService::setPostRemoved($id, true)
            : ($action === 'restore' ? AdminService::setPostRemoved($id, false) : false);
        $ok ? JsonResponse::ok([], 'admin.content.updated') : JsonResponse::fail(422, 'validation.invalid');
    }

    public function commentAction(Request $req): void
    {
        $id = (int) $req->post('comment_id', 0);
        $action = (string) $req->post('action', '');
        $ok = $action === 'remove' ? AdminService::setCommentRemoved($id, true)
            : ($action === 'restore' ? AdminService::setCommentRemoved($id, false) : false);
        $ok ? JsonResponse::ok([], 'admin.content.updated') : JsonResponse::fail(422, 'validation.invalid');
    }

    // ==================================================================
    // 群组管理
    // ==================================================================

    public function groups(Request $req): void
    {
        $keyword = trim((string) $req->get('q', ''));
        $state = (string) $req->get('state', 'active');
        $page = max(1, (int) $req->get('page', 1));
        echo View::render('admin/groups', [
            'list'    => AdminService::groups($keyword, $state, $page),
            'keyword' => $keyword,
            'state'   => $state,
            'seg'     => 'groups',
        ], 'admin');
    }

    public function groupAction(Request $req): void
    {
        $id = (int) $req->post('group_id', 0);
        $action = (string) $req->post('action', '');
        $ok = $action === 'disband' ? AdminService::disbandGroup($id)
            : ($action === 'restore' ? AdminService::restoreGroup($id) : false);
        $ok ? JsonResponse::ok([], 'admin.group.updated') : JsonResponse::fail(422, 'validation.invalid');
    }

    // ==================================================================
    // 黑名单
    // ==================================================================

    public function blocked(Request $req): void
    {
        $keyword = trim((string) $req->get('q', ''));
        $page = max(1, (int) $req->get('page', 1));
        echo View::render('admin/blocked', [
            'list'    => AdminService::blockedUsers($keyword, $page),
            'keyword' => $keyword,
            'seg'     => 'blocked',
        ], 'admin');
    }

    public function blockAction(Request $req): void
    {
        AdminService::removeBlock((int) $req->post('id', 0))
            ? JsonResponse::ok([], 'admin.blocked.removed')
            : JsonResponse::fail(422, 'validation.invalid');
    }

    // ==================================================================
    // 举报处理
    // ==================================================================

    public function reports(Request $req): void
    {
        $status = (string) $req->get('status', 'pending');
        $type = (string) $req->get('type', '');
        $page = max(1, (int) $req->get('page', 1));
        echo View::render('admin/reports', [
            'list'    => ReportService::list($status, $type, $page),
            'counts'  => ReportService::counts(),
            'status'  => $status,
            'type'    => $type,
            'reasons' => ReportService::REASONS,
            'seg'     => 'reports',
        ], 'admin');
    }

    public function reportAction(Request $req): void
    {
        $result = ReportService::handle(
            (int) $req->post('report_id', 0),
            (string) $req->post('action', ''),
            (string) $req->post('note', '')
        );
        if (!$result['ok']) {
            JsonResponse::fail(422, (string) $result['error']);
            return;
        }
        JsonResponse::ok(['status' => $result['status'] ?? ''], 'admin.report.handled');
    }

    // ==================================================================
    // 角色与权限
    // ==================================================================

    public function roles(Request $req): void
    {
        $editId = (int) $req->get('role', 0);
        $roleList = AdminService::roles();
        if ($editId <= 0 && $roleList) {
            $editId = (int) $roleList[0]['id'];
        }
        $current = null;
        foreach ($roleList as $r) {
            if ((int) $r['id'] === $editId) {
                $current = $r;
            }
        }
        echo View::render('admin/roles', [
            'roles'       => $roleList,
            'permissions' => AdminService::permissions(),
            'granted'     => $editId > 0 ? AdminService::rolePermissionIds($editId) : [],
            'current'     => $current,
            'seg'         => 'roles',
        ], 'admin');
    }

    public function roleSave(Request $req): void
    {
        $roleId = (int) $req->post('role_id', 0);
        $perms = (array) $req->post('permissions', []);
        AdminService::saveRolePermissions($roleId, $perms)
            ? JsonResponse::ok([], 'admin.role.saved')
            : JsonResponse::fail(422, 'admin.role.locked');
    }

    public function roleCreate(Request $req): void
    {
        $id = AdminService::createRole(
            (string) $req->post('name', ''),
            (string) $req->post('slug', ''),
            (string) $req->post('description', '')
        );
        $id > 0 ? JsonResponse::created(['id' => $id], 'admin.role.created') : JsonResponse::fail(422, 'admin.role.exists');
    }

    public function roleDelete(Request $req): void
    {
        AdminService::deleteRole((int) $req->post('role_id', 0))
            ? JsonResponse::ok([], 'admin.role.deleted')
            : JsonResponse::fail(422, 'admin.role.builtin_ro');
    }

    // ==================================================================
    // 站点设置 / 主题语言
    // ==================================================================

    public function settings(): void
    {
        echo View::render('admin/settings', [
            'settings' => SiteSettingsService::all(),
            'waf_mode' => WafService::mode(),
            'seg'      => 'settings',
        ], 'admin');
    }

    public function settingsSave(Request $req): void
    {
        $pairs = [];
        foreach (SiteSettingsService::WRITABLE as $key) {
            $v = $req->post($key, null);
            if ($v === null) {
                continue;
            }
            if (in_array($key, ['maintenance', 'allow_registration', 'smtp_dev_mode'], true)) {
                $v = ((int) $v) === 1 ? '1' : '0';
            }
            if ($key === 'waf_mode') {
                $v = $v === 'defense' ? 'defense' : 'observe';
            }
            if ($key === 'register_mode' && !in_array($v, ['open', 'email', 'invite'], true)) {
                continue;
            }
            $pairs[$key] = (string) $v;
        }
        $n = SiteSettingsService::setMany($pairs, 'general');
        AdminService::log('setting.save', 'setting', null, (string) $n);
        JsonResponse::ok(['saved' => $n], 'admin.setting.saved');
    }

    public function appearance(): void
    {
        echo View::render('admin/appearance', [
            'themes'    => AdminService::themes(),
            'languages' => AdminService::languages(),
            'seg'       => 'appearance',
        ], 'admin');
    }

    public function appearanceSave(Request $req): void
    {
        $action = (string) $req->post('action', '');
        $id = (int) $req->post('id', 0);
        $code = (string) $req->post('code', '');
        $on = (int) $req->post('on', 0) === 1;
        $ok = false;
        switch ($action) {
            case 'theme_toggle':   $ok = AdminService::setThemeEnabled($id, $on); break;
            case 'theme_default':  $ok = AdminService::setDefaultTheme($code); break;
            case 'lang_toggle':    $ok = AdminService::setLanguageEnabled($id, $on); break;
            case 'lang_default':   $ok = AdminService::setDefaultLanguage($code); break;
        }
        $ok ? JsonResponse::ok([], 'admin.appearance.saved') : JsonResponse::fail(422, 'validation.invalid');
    }

    // ==================================================================
    // 日志
    // ==================================================================

    public function logs(Request $req): void
    {
        $tab = $req->get('tab', 'admin') === 'login' ? 'login' : 'admin';
        $page = max(1, (int) $req->get('page', 1));
        $keyword = trim((string) $req->get('q', ''));
        $result = (string) $req->get('result', '');
        echo View::render('admin/logs', [
            'tab'     => $tab,
            'keyword' => $keyword,
            'result'  => $result,
            'admin'   => $tab === 'admin' ? AdminService::adminLogs($page, $keyword) : null,
            'login'   => $tab === 'login' ? AdminService::loginLogs(['result' => $result, 'ip' => $keyword], $page) : null,
            'seg'     => 'logs',
        ], 'admin');
    }

    // ==================================================================
    // WAF
    // ==================================================================

    public function waf(Request $req): void
    {
        $db = Database::instance();
        $rules = $db->fetchAll('SELECT * FROM waf_rules ORDER BY category, id');
        $logs = AdminService::wafLogs([
            'action'    => (string) $req->get('action', ''),
            'ip'        => trim((string) $req->get('ip', '')),
            'min_score' => (int) $req->get('min_score', 0),
        ], max(1, (int) $req->get('page', 1)));
        $bans = $db->fetchAll('SELECT * FROM ip_bans ORDER BY created_at DESC LIMIT 100');
        echo View::render('admin/waf', [
            'rules'    => $rules,
            'logs'     => $logs,
            'bans'     => $bans,
            'mode'     => WafService::mode(),
            'filters'  => [
                'action'    => (string) $req->get('action', ''),
                'ip'        => trim((string) $req->get('ip', '')),
                'min_score' => (int) $req->get('min_score', 0),
            ],
            'seg' => 'waf',
        ], 'admin');
    }

    public function wafRuleToggle(Request $req): void
    {
        $id = (int) $req->post('id', 0);
        $enabled = (int) $req->post('enabled', 0);
        if ($id <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        Database::instance()->update('waf_rules', ['enabled' => $enabled ? 1 : 0], 'id = ?', [$id]);
        AdminService::log('waf.rule_toggle', 'waf_rule', $id, $enabled ? 'on' : 'off');
        JsonResponse::ok([], 'ok');
    }

    public function wafMode(Request $req): void
    {
        $mode = (string) $req->post('mode', '') === 'defense' ? 'defense' : 'observe';
        SiteSettingsService::set('waf_mode', $mode, 'security');
        AdminService::log('waf.mode', 'setting', null, $mode);
        JsonResponse::ok(['mode' => $mode], 'admin.waf.mode_saved');
    }

    public function banIp(Request $req): void
    {
        $ip = trim((string) $req->post('ip', ''));
        $reason = trim((string) $req->post('reason', ''));
        $days = (int) $req->post('days', 0);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            JsonResponse::fail(422, 'validation.invalid', ['ip' => __('validation.invalid')]);
            return;
        }
        $expires = $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
        Database::instance()->insert('ip_bans', [
            'ip' => $ip, 'reason' => mb_substr($reason, 0, 128), 'expires_at' => $expires, 'created_at' => now_utc(),
        ]);
        AdminService::log('waf.ban_ip', 'ip', null, $ip);
        JsonResponse::ok([], 'admin.waf.banned');
    }

    public function unbanIp(Request $req): void
    {
        $ip = trim((string) $req->post('ip', ''));
        if ($ip === '') {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        Database::instance()->delete('ip_bans', 'ip = ?', [$ip]);
        AdminService::log('waf.unban_ip', 'ip', null, $ip);
        JsonResponse::ok([], 'admin.waf.unbanned');
    }

    // ==================================================================
    // 更新
    // ==================================================================

    public function update(): void
    {
        echo View::render('admin/update', [
            'info'       => UpdateService::check(),
            'migrations' => MigrationService::status(),
            'backups'    => UpdateService::backups(),
            'seg'        => 'update',
        ], 'admin');
    }

    public function updateDownload(Request $req): void
    {
        $url = trim((string) $req->post('url', ''));
        $result = UpdateService::download($url);
        if (!$result['ok']) {
            JsonResponse::fail(422, $result['error'] !== '' ? $result['error'] : 'update.fetch_failed');
            return;
        }
        AdminService::log('update.download', 'update', null, basename($result['path']));
        JsonResponse::ok(['path' => $result['path'], 'bytes' => $result['bytes']], 'admin.update.downloaded');
    }

    public function updateMigrate(Request $req): void
    {
        $result = MigrationService::applyPending();
        if (!empty($result['failed'])) {
            $file = array_key_first($result['failed']);
            JsonResponse::fail(500, 'admin.update.migration_failed', ['file' => (string) $file]);
            return;
        }
        JsonResponse::ok(['applied' => $result['applied']], 'admin.update.migrated');
    }
}
