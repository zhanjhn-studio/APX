<?php
/** Admin · 主题与语言 */
use App\Core\I18n;

$themes = $themes ?? [];
$languages = $languages ?? [];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.appearance')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.appearance.subtitle')) ?></div>
  </div>
</div>

<div class="apx-card" style="margin-bottom:18px;">
  <div class="apx-card__title"><?= e(__('theme.title')) ?> · <?= count($themes) ?></div>
  <div class="apx-theme-admin">
    <?php foreach ($themes as $t): ?>
      <div class="apx-theme-admin__item<?= (int) $t['is_enabled'] === 1 ? '' : ' is-off' ?>">
        <div class="apx-theme-admin__bar" data-apx-theme="<?= e($t['slug']) ?>" style="background:linear-gradient(135deg,var(--brand-1),var(--brand-3));"></div>
        <div class="apx-theme-admin__body">
          <div class="apx-theme-admin__name">
            <?= e(I18n::translate('theme.name.' . $t['slug'])) ?>
            <?php if ((int) $t['is_default'] === 1): ?><span class="apx-badge apx-badge--primary"><?= e(__('admin.appearance.default')) ?></span><?php endif; ?>
          </div>
          <div class="apx-theme-admin__slug"><?= e($t['slug']) ?></div>
        </div>
        <div class="apx-theme-admin__actions">
          <label class="apx-switch" title="<?= e(__('admin.appearance.enable')) ?>">
            <input type="checkbox" data-theme-toggle="<?= (int) $t['id'] ?>" <?= (int) $t['is_enabled'] === 1 ? 'checked' : '' ?>>
            <span></span>
          </label>
          <?php if ((int) $t['is_default'] !== 1): ?>
            <button class="apx-btn apx-btn--ghost apx-btn--sm" data-theme-default="<?= e($t['slug']) ?>"><?= e(__('admin.appearance.set_default')) ?></button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="apx-card">
  <div class="apx-card__title"><?= e(__('admin.appearance.languages')) ?> · <?= count($languages) ?></div>
  <?php foreach ($languages as $l): ?>
    <div class="apx-lang-item">
      <div>
        <div class="apx-text-strong" style="font-weight:600;">
          <?= e($l['name']) ?>
          <?php if ((int) $l['is_default'] === 1): ?><span class="apx-badge apx-badge--primary"><?= e(__('admin.appearance.default')) ?></span><?php endif; ?>
        </div>
        <div class="apx-text-faint" style="font-size:11px;"><?= e($l['code']) ?></div>
      </div>
      <div class="apx-flex apx-gap-2" style="align-items:center;">
        <label class="apx-switch">
          <input type="checkbox" data-lang-toggle="<?= (int) $l['id'] ?>" <?= (int) $l['is_enabled'] === 1 ? 'checked' : '' ?>>
          <span></span>
        </label>
        <?php if ((int) $l['is_default'] !== 1): ?>
          <button class="apx-btn apx-btn--ghost apx-btn--sm" data-lang-default="<?= e($l['code']) ?>"><?= e(__('admin.appearance.set_default')) ?></button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
