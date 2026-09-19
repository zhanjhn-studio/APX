<?php /** Partial: 验证码字段 */ ?>
<div class="apx-field">
  <label class="apx-field__label"><?= e(__('captcha.label')) ?></label>
  <div class="apx-captcha-row">
    <input class="apx-input" type="text" name="captcha" autocomplete="off" inputmode="latin" maxlength="6" required>
    <img class="apx-captcha-img" src="<?= route('/captcha') ?>" alt="captcha" title="<?= e(__('captcha.label')) ?>" onclick="this.src=this.src.split('?')[0]+'?'+Date.now()">
  </div>
  <div class="apx-field__error"></div>
</div>
