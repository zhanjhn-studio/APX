<?php /** Auth · Forgot password */ ?>
<h1><?= __('auth.forgot.title') ?></h1>
<p class="apx-sub"><?= __('auth.forgot.subtitle') ?></p>

<form action="<?= route('/forgot-password') ?>" method="post" data-apx-submit novalidate>
  <?= csrf_field() ?>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-forgot-email" type="email" name="email" value="<?= e(old('email')) ?>" placeholder=" " autocomplete="email" required>
    <label class="apx-field__label" for="apx-forgot-email"><?= __('auth.register.email_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <div class="apx-field__error"></div>
  </div>
  <?= \App\Core\View::partial('captcha') ?>
  <button class="apx-btn apx-btn--primary apx-btn--block" type="submit"><span class="apx-btn-label"><?= __('auth.forgot.submit') ?></span></button>
</form>

<div class="apx-auth__alt">
  <a href="<?= route('/login') ?>"><?= __('auth.to_login') ?></a>
</div>
