<?php
/** Admin · 系统更新与数据库迁移 */
$info = $info ?? ['channel' => 'tags', 'current' => '', 'latest' => null, 'has_update' => false, 'notes' => '', 'url' => '', 'download' => '', 'published_at' => '', 'error' => ''];
$migrations = $migrations ?? ['pending' => [], 'applied' => []];
$backups = $backups ?? [];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.update.title')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.update.channel')) ?>：<?= e(__('admin.update.channel.' . ($info['channel'] ?? 'tags'))) ?></div>
  </div>
</div>

<div class="apx-card apx-card--glass" style="margin-bottom:18px;">
  <div class="apx-card__title"><?= e(__('admin.update.title')) ?></div>
  <div class="apx-update-row"><span><?= e(__('admin.update.current')) ?></span><b><?= e($info['current']) ?></b></div>
  <div class="apx-update-row"><span><?= e(__('admin.update.latest')) ?></span><b><?= e($info['latest'] ?? '—') ?></b></div>
  <?php if (!empty($info['published_at'])): ?>
  <div class="apx-update-row"><span><?= e(__('admin.update.released_at')) ?></span><b><?= e(substr((string) $info['published_at'], 0, 10)) ?></b></div>
  <?php endif; ?>

  <?php if ($info['error']): ?>
    <p class="apx-alert apx-alert--warning" style="margin-top:14px;">
      <?= e(__($info['error'])) ?>
      <?php if ($info['error'] === 'update.not_configured'): ?>
        — <?= e(__('admin.update.cfg_hint')) ?>
      <?php endif; ?>
    </p>
  <?php elseif ($info['has_update']): ?>
    <div class="apx-alert apx-alert--info" style="margin-top:14px;">
      <b><?= e(__('admin.update.available')) ?> v<?= e($info['latest']) ?></b>
      <?php if ($info['notes']): ?><p class="apx-card__hint" style="white-space:pre-wrap;"><?= e($info['notes']) ?></p><?php endif; ?>
      <div class="apx-flex apx-gap-2 apx-mt-2" style="flex-wrap:wrap;">
        <?php if (!empty($info['download'])): ?>
          <button class="apx-btn apx-btn--primary" data-update-download="<?= e($info['download']) ?>"><?= e(__('admin.update.download_now')) ?></button>
        <?php endif; ?>
        <?php if (!empty($info['url'])): ?>
          <a class="apx-btn apx-btn--ghost" href="<?= e($info['url']) ?>" target="_blank" rel="noopener"><?= e(__('admin.update.go')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <p class="apx-alert apx-alert--success" style="margin-top:14px;"><?= e(__('admin.update.up_to_date')) ?></p>
  <?php endif; ?>

  <p class="apx-card__hint apx-mt-3"><?= e(__('admin.update.apply_hint')) ?></p>
</div>

<div class="apx-card" style="margin-bottom:18px;">
  <div class="apx-card__title">
    <?= e(__('admin.update.migrations')) ?>
    <span class="apx-badge <?= empty($migrations['pending']) ? 'apx-badge--success' : 'apx-badge--warning' ?>" style="margin-left:8px;">
      <?= e(__('admin.update.pending')) ?> <?= count($migrations['pending'] ?? []) ?>
    </span>
    <?php if (!empty($migrations['pending'])): ?>
      <button class="apx-btn apx-btn--primary apx-btn--sm" style="margin-left:auto;" data-update-migrate><?= e(__('admin.update.run_migrations')) ?></button>
    <?php endif; ?>
  </div>
  <p class="apx-card__hint"><?= e(__('admin.update.migration_hint')) ?></p>

  <?php if (empty($migrations['pending'])): ?>
    <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('admin.update.no_pending')) ?></div>
  <?php else: ?>
    <div class="apx-mt-2">
      <?php foreach ($migrations['pending'] as $f): ?>
        <div class="apx-update-row"><span class="apx-mono"><?= e($f) ?></span><b class="apx-badge apx-badge--warning"><?= e(__('admin.update.pending')) ?></b></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($migrations['applied'])): ?>
    <div class="apx-card__title apx-mt-3"><?= e(__('admin.update.applied')) ?></div>
    <div class="apx-mt-2" style="max-height:200px;overflow:auto;">
      <?php foreach (array_reverse($migrations['applied']) as $f): ?>
        <div class="apx-update-row"><span class="apx-mono"><?= e($f) ?></span><b class="apx-badge apx-badge--success">✓</b></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="apx-card">
  <div class="apx-card__title"><?= e(__('admin.update.backups')) ?></div>
  <p class="apx-card__hint"><?= e(__('admin.update.backup_hint')) ?></p>
  <?php if (empty($backups)): ?>
    <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('admin.update.no_backups')) ?></div>
  <?php else: ?>
    <?php foreach ($backups as $b): ?>
      <div class="apx-update-row">
        <span class="apx-mono"><?= e($b['name']) ?></span>
        <b><?= e($b['time']) ?> · <?= number_format($b['size'] / 1024, 1) ?> KB</b>
      </div>
    <?php endforeach; ?>
    <p class="apx-card__hint apx-mt-2"><?= e(__('admin.update.rollback_hint')) ?></p>
  <?php endif; ?>
</div>
