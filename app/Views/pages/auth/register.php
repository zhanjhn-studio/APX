<?php /** Auth · Register */
use App\Core\Config;
$mode = Config::get('app.register_mode', 'open');
?>
<div class="apx-auth__head">
  <h1><?= __('auth.register.title') ?></h1>
  <p class="apx-sub"><?= __('auth.register.subtitle') ?></p>
</div>

<form action="<?= route('/register') ?>" method="post" data-apx-submit data-success-redirect="<?= route('/home') ?>" novalidate>
  <?= csrf_field() ?>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-reg-name" type="text" name="username" value="<?= e(old('username')) ?>" placeholder=" " autocomplete="username" required>
    <label class="apx-field__label" for="apx-reg-name"><?= __('auth.register.username_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <div class="apx-field__error"></div>
  </div>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-reg-email" type="email" name="email" value="<?= e(old('email')) ?>" placeholder=" " autocomplete="email" required>
    <label class="apx-field__label" for="apx-reg-email"><?= __('auth.register.email_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <div class="apx-field__error"></div>
  </div>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-reg-nick" type="text" name="nickname" value="<?= e(old('nickname')) ?>" placeholder=" " autocomplete="nickname">
    <label class="apx-field__label" for="apx-reg-nick"><?= __('auth.register.nickname_placeholder') ?></label>
    <span class="apx-field__bar"></span>
  </div>
  <div class="apx-field apx-field--wave apx-field--pwd">
    <input class="apx-input" id="apx-reg-pwd" type="password" name="password" placeholder=" " autocomplete="new-password" required>
    <label class="apx-field__label" for="apx-reg-pwd"><?= __('auth.register.password_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <button type="button" class="apx-field__toggle" data-toggle-pwd="apx-reg-pwd" aria-label="<?= e(__('auth.register.password_placeholder')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
    <div class="apx-pwd-strength" id="apx-pwd-strength"><i></i></div>
  </div>
  <?php if ($mode === 'invite'): ?>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-reg-invite" type="text" name="invite_code" value="<?= e(old('invite_code')) ?>" placeholder=" " required>
    <label class="apx-field__label" for="apx-reg-invite"><?= __('auth.register.invite_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <div class="apx-field__error"></div>
  </div>
  <?php endif; ?>
  <?= \App\Core\View::partial('captcha') ?>
  <button class="apx-btn apx-btn--primary apx-btn--block apx-mt-2" type="submit"><span class="apx-btn-label"><?= __('auth.register.submit') ?></span></button>
</form>

<div class="apx-auth__alt">
  <?= __('auth.has_account') ?>
  <a href="<?= route('/login') ?>"><?= __('auth.to_login') ?></a>
</div>

<script type="module">
  const pwd = document.getElementById('apx-reg-pwd');
  const bar = document.getElementById('apx-pwd-strength');
  const score = (v) => { let s = 0; if (v.length >= 8) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++; return Math.min(s, 4); };
  pwd.addEventListener('input', () => { const lvl = score(pwd.value); bar.className = 'apx-pwd-strength lvl-' + lvl; });
  document.querySelectorAll('[data-toggle-pwd]').forEach((b) => b.addEventListener('click', () => {
    const i = document.getElementById(b.getAttribute('data-toggle-pwd'));
    i.type = i.type === 'password' ? 'text' : 'password';
  }));
</script>
