<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\TotpService;

/**
 * 认证控制器：登录 / 注册 / 找回 / 重置 / 邮箱验证 / 退出。
 * 写操作返回统一 JSON，前端据此跳转或提示。
 */
class AuthController
{
    public function showLogin(): void
    {
        if (AuthService::user()) {
            redirect('/home');
        }
        echo View::render('auth/login', [], 'auth');
    }

    public function login(Request $req): void
    {
        $login = (string) $req->post('login', '');
        $password = (string) $req->post('password', '');
        $remember = (bool) $req->post('remember');

        if ($login === '') {
            JsonResponse::fail(422, 'auth.login.username_empty', []);
        }
        if ($password === '') {
            JsonResponse::fail(422, 'auth.login.password_empty', []);
        }

        $user = AuthService::authenticate($login, $password);
        if (!$user) {
            $this->recordFail($login);
            JsonResponse::fail(401, 'auth.login.invalid', []);
        }

        if ($user['status'] === 'pending') {
            JsonResponse::fail(403, 'auth.login.not_verified', []);
        }
        if ($user['status'] === 'banned') {
            JsonResponse::fail(403, 'auth.login.banned', []);
        }

        if ($user['two_factor_enabled']) {
            Session::set('pending_2fa_user', $user['id']);
            JsonResponse::ok(['require_totp' => true], 'auth.login.totp_required');
        }

        $this->finishLogin((int) $user['id'], $remember);
    }

    public function login2fa(Request $req): void
    {
        $userId = Session::get('pending_2fa_user');
        if (!$userId) {
            JsonResponse::fail(401, 'auth.login.expired', []);
        }
        $code = (string) $req->post('code', '');
        $row = Database::instance()->fetch('SELECT secret FROM user_totp WHERE user_id = ? AND confirmed_at IS NOT NULL LIMIT 1', [$userId]);
        if (!$row || !TotpService::verify($row['secret'], $code)) {
            JsonResponse::fail(401, 'auth.login.totp_invalid', []);
        }
        Session::forget('pending_2fa_user');
        $this->finishLogin((int) $userId, (bool) $req->post('remember'));
    }

    private function finishLogin(int $userId, bool $remember): void
    {
        AuthService::login($userId, $remember);
        \App\Services\DeviceService::register($userId);
        Database::instance()->insert('login_logs', [
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'result' => 'success',
            'created_at' => now_utc(),
        ]);
        Database::instance()->update('users', ['last_active_at' => now_utc()], 'id = ?', [$userId]);
        JsonResponse::ok(['redirect' => route('/home')], 'auth.login.success');
    }

    private function recordFail(string $login): void
    {
        Database::instance()->insert('login_logs', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'result' => 'fail',
            'created_at' => now_utc(),
        ]);
    }

    public function showRegister(): void
    {
        if (AuthService::user()) {
            redirect('/home');
        }
        echo View::render('auth/register', [], 'auth');
    }

    public function register(Request $req): void
    {
        $data = [
            'username' => trim((string) $req->post('username', '')),
            'email'    => trim((string) $req->post('email', '')),
            'nickname' => trim((string) $req->post('nickname', '')),
            'password' => (string) $req->post('password', ''),
        ];
        if ($data['nickname'] === '') {
            $data['nickname'] = $data['username'];
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $data['username'])) {
            JsonResponse::fail(422, 'validation.username', []);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            JsonResponse::fail(422, 'validation.email', []);
        }
        if (mb_strlen($data['password']) < 8) {
            JsonResponse::fail(422, 'validation.password_min', []);
        }
        $captcha = trim((string) $req->post('captcha', ''));
        if (!\App\Services\CaptchaService::verify($captcha)) {
            JsonResponse::fail(422, 'captcha.invalid', ['captcha' => __('captcha.invalid')]);
        }
        if (AuthService::isUsernameTaken($data['username'])) {
            JsonResponse::fail(409, 'auth.register.username_taken', []);
        }
        if (AuthService::isEmailTaken($data['email'])) {
            JsonResponse::fail(409, 'auth.register.email_taken', []);
        }

        if (!\App\Services\SiteSettingsService::bool('allow_registration', true)) {
            JsonResponse::fail(403, 'auth.register.closed', []);
            return;
        }
        $mode = \App\Services\SiteSettingsService::get('register_mode', null)
            ?: \App\Core\Config::get('app.register_mode', 'open');
        if ($mode === 'invite') {
            $code = trim((string) $req->post('invite_code', ''));
            $inv = Database::instance()->fetch(
                'SELECT * FROM invite_codes WHERE code = ? AND used_by IS NULL AND (expires_at IS NULL OR expires_at > ?) LIMIT 1',
                [$code, now_utc()]
            );
            if (!$inv) {
                JsonResponse::fail(422, 'auth.register.invalid_invite', []);
            }
        }

        $userId = AuthService::register($data);

        if ($mode === 'invite' && isset($inv)) {
            Database::instance()->update('invite_codes', ['used_by' => $userId, 'used_at' => now_utc()], 'id = ?', [$inv['id']]);
        }

        if ($mode === 'email') {
            $token = bin2hex(random_bytes(32));
            Database::instance()->insert('email_tokens', [
                'user_id' => $userId,
                'token' => $token,
                'type' => 'verify',
                'expires_at' => date('Y-m-d H:i:s', time() + 86400),
                'created_at' => now_utc(),
            ]);
            $link = rtrim(\App\Core\Config::get('app.base_url') ?: '', '/') . '/verify-email/' . $token;
            MailService::queue($data['email'], __('mail.verify.subject'), '<p>' . __('mail.verify.body', [':link' => $link]) . '</p>', __('mail.verify.body', [':link' => $link]));
        }

        AuthService::login($userId, false);
        Database::instance()->insert('login_logs', [
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'result' => 'success',
            'created_at' => now_utc(),
        ]);
        JsonResponse::ok(['redirect' => route('/home')], 'auth.register.success');
    }

    public function showForgot(): void
    {
        echo View::render('auth/forgot', [], 'auth');
    }

    public function forgot(Request $req): void
    {
        $captcha = trim((string) $req->post('captcha', ''));
        if (!\App\Services\CaptchaService::verify($captcha)) {
            JsonResponse::fail(422, 'captcha.invalid', ['captcha' => __('captcha.invalid')]);
        }
        $email = trim((string) $req->post('email', ''));
        $user = Database::instance()->fetch('SELECT id, email FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            Database::instance()->insert('email_tokens', [
                'user_id' => $user['id'],
                'token' => $token,
                'type' => 'reset',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => now_utc(),
            ]);
            $link = rtrim(\App\Core\Config::get('app.base_url') ?: '', '/') . '/reset-password/' . $token;
            MailService::queue($user['email'], __('mail.reset.subject'), '<p>' . __('mail.reset.body', [':link' => $link]) . '</p>', __('mail.reset.body', [':link' => $link]));
        }
        // 不泄露邮箱是否存在
        JsonResponse::ok([], 'auth.forgot.sent');
    }

    public function showReset(Request $req, string $token): void
    {
        $row = Database::instance()->fetch(
            'SELECT id FROM email_tokens WHERE token = ? AND type = ? AND used_at IS NULL AND expires_at > ? LIMIT 1',
            [$token, 'reset', now_utc()]
        );
        if (!$row) {
            echo View::render('auth/message', ['type' => 'error', 'title' => __('auth.reset.invalid.title'), 'text' => __('auth.reset.invalid.text')], 'auth');
            return;
        }
        echo View::render('auth/reset', ['token' => $token], 'auth');
    }

    public function reset(Request $req): void
    {
        $token = (string) $req->post('token', '');
        $password = (string) $req->post('password', '');
        if (mb_strlen($password) < 8) {
            JsonResponse::fail(422, 'validation.password_min', []);
        }
        $row = Database::instance()->fetch(
            'SELECT id, user_id FROM email_tokens WHERE token = ? AND type = ? AND used_at IS NULL AND expires_at > ? LIMIT 1',
            [$token, 'reset', now_utc()]
        );
        if (!$row) {
            JsonResponse::fail(422, 'auth.reset.invalid', []);
        }
        Database::instance()->update('users', ['password_hash' => AuthService::hashPassword($password)], 'id = ?', [$row['user_id']]);
        Database::instance()->update('email_tokens', ['used_at' => now_utc()], 'id = ?', [$row['id']]);
        JsonResponse::ok(['redirect' => route('/login')], 'auth.reset.success');
    }

    public function verifyEmail(Request $req, string $token): void
    {
        $row = Database::instance()->fetch(
            'SELECT id, user_id FROM email_tokens WHERE token = ? AND type = ? AND used_at IS NULL AND expires_at > ? LIMIT 1',
            [$token, 'verify', now_utc()]
        );
        if (!$row) {
            echo View::render('auth/message', ['type' => 'error', 'title' => __('auth.verify.invalid.title'), 'text' => __('auth.verify.invalid.text')], 'auth');
            return;
        }
        Database::instance()->update('users', ['status' => 'active', 'email_verified_at' => now_utc()], 'id = ?', [$row['user_id']]);
        Database::instance()->update('email_tokens', ['used_at' => now_utc()], 'id = ?', [$row['id']]);
        echo View::render('auth/message', ['type' => 'success', 'title' => __('auth.verify.success.title'), 'text' => __('auth.verify.success.text')], 'auth');
    }

    public function logout(): void
    {
        \App\Services\DeviceService::removeCurrent();
        AuthService::logout();
        redirect('/login');
    }
}
