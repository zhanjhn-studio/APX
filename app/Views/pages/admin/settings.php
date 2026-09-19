<?php
/** Admin · 站点设置 */
$settings = $settings ?? [];
$wafMode = $waf_mode ?? 'observe';
$val = fn(string $k, string $d = ''): string => (string) ($settings[$k] ?? $d);
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.settings')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.setting.subtitle')) ?></div>
  </div>
</div>

<form id="apx-site-settings">
  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.setting.group.general')) ?></div>
    <div class="apx-form-grid">
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.setting.site_name')) ?></span>
        <input class="apx-input" name="site_name" value="<?= e($val('site_name', 'APX')) ?>" maxlength="64">
      </label>
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.setting.site_slogan')) ?></span>
        <input class="apx-input" name="site_slogan" value="<?= e($val('site_slogan')) ?>" maxlength="128">
      </label>
      <label class="apx-field apx-field--wide">
        <span class="apx-field__label"><?= e(__('admin.setting.announcement')) ?></span>
        <textarea class="apx-textarea" name="announcement" rows="2" placeholder="<?= e(__('admin.setting.announcement_hint')) ?>"><?= e($val('announcement')) ?></textarea>
      </label>
      <label class="apx-switch-row">
        <span><?= e(__('admin.setting.maintenance')) ?></span>
        <span class="apx-switch">
          <input type="checkbox" name="maintenance" value="1" <?= $val('maintenance', '0') === '1' ? 'checked' : '' ?>>
          <span></span>
        </span>
      </label>
    </div>
  </div>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.setting.group.auth')) ?></div>
    <div class="apx-form-grid">
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.setting.register_mode')) ?></span>
        <select class="apx-input" name="register_mode">
          <?php foreach (['open', 'email', 'invite'] as $m): ?>
            <option value="<?= e($m) ?>" <?= $val('register_mode', 'open') === $m ? 'selected' : '' ?>><?= e(__('admin.setting.register_mode.' . $m)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="apx-switch-row">
        <span><?= e(__('admin.setting.allow_registration')) ?></span>
        <span class="apx-switch">
          <input type="checkbox" name="allow_registration" value="1" <?= $val('allow_registration', '1') === '1' ? 'checked' : '' ?>>
          <span></span>
        </span>
      </label>
      <label class="apx-switch-row">
        <span><?= e(__('admin.setting.smtp_dev_mode')) ?></span>
        <span class="apx-switch">
          <input type="checkbox" name="smtp_dev_mode" value="1" <?= $val('smtp_dev_mode', '1') === '1' ? 'checked' : '' ?>>
          <span></span>
        </span>
      </label>
    </div>
  </div>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.setting.group.appearance')) ?></div>
    <div class="apx-form-grid">
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.setting.default_theme')) ?></span>
        <input class="apx-input" name="default_theme" value="<?= e($val('default_theme', 'mono')) ?>" maxlength="32">
      </label>
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.setting.default_mode')) ?></span>
        <select class="apx-input" name="default_mode">
          <?php foreach (['dark', 'light', 'auto'] as $m): ?>
            <option value="<?= e($m) ?>" <?= $val('default_mode', 'dark') === $m ? 'selected' : '' ?>><?= e(__('theme.mode.' . $m)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.setting.default_language')) ?></span>
        <select class="apx-input" name="default_language">
          <?php foreach (['zh-CN', 'zh-TW', 'en'] as $l): ?>
            <option value="<?= e($l) ?>" <?= $val('default_language', 'zh-CN') === $l ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.setting.group.security')) ?></div>
    <div class="apx-form-grid">
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.waf.mode')) ?></span>
        <select class="apx-input" name="waf_mode">
          <option value="observe" <?= $wafMode === 'observe' ? 'selected' : '' ?>><?= e(__('admin.waf.mode_observe')) ?></option>
          <option value="defense" <?= $wafMode === 'defense' ? 'selected' : '' ?>><?= e(__('admin.waf.mode_defense')) ?></option>
        </select>
      </label>
    </div>
    <p class="apx-card__hint"><?= e(__('admin.waf.mode_hint')) ?></p>
  </div>

  <button class="apx-btn apx-btn--primary" type="submit"><?= e(__('common.action.save')) ?></button>
</form>
