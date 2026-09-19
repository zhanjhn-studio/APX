<?php /** Auth · Reset password. $token provided by controller. */ ?>
<h1><?= __('auth.reset.title') ?></h1>
<p class="apx-sub"><?= __('auth.reset.invalid') ?></p>

<form action="<?= route('/reset-password') ?>" method="post" data-apx-submit data-success-redirect="<?= route('/login') ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
  <div class="apx-field apx-field--wave apx-field--pwd">
    <input class="apx-input" id="apx-reset-pwd" type="password" name="password" placeholder=" " autocomplete="new-password" required>
    <label class="apx-field__label" for="apx-reset-pwd"><?= __('auth.reset.password_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <button type="button" class="apx-field__toggle" data-toggle-pwd="apx-reset-pwd" aria-label="<?= e(__('auth.reset.password_placeholder')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
    <div class="apx-field__error"></div>
  </div>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-reset-confirm" type="password" name="password_confirm" placeholder=" " autocomplete="new-password" required>
    <label class="apx-field__label" for="apx-reset-confirm"><?= __('auth.reset.confirm_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <div class="apx-field__error"></div>
  </div>
  <button class="apx-btn apx-btn--primary apx-btn--block" type="submit"><span class="apx-btn-label"><?= __('auth.reset.submit') ?></span></button>
</form>

<div class="apx-auth__alt">
  <a href="<?= route('/login') ?>"><?= __('auth.to_login') ?></a>
</div>

<script type="module">
  const form = document.querySelector('form[data-apx-submit]');
  form.addEventListener('submit', (e) => {
    const p = form.querySelector('[name=password]').value;
    const c = form.querySelector('[name=password_confirm]').value;
    if (p !== c) { e.preventDefault(); alert('<?= __('auth.reset.confirm_placeholder') ?>'); }
  });
  document.querySelectorAll('[data-toggle-pwd]').forEach((b) => b.addEventListener('click', () => {
    const i = document.getElementById(b.getAttribute('data-toggle-pwd'));
    i.type = i.type === 'password' ? 'text' : 'password';
  }));
</script>
