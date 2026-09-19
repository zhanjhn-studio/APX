<?php
/** Admin · 概览 */
$stats = $stats ?? [];
$reports = $reports ?? ['pending' => 0];
$trend = $trend ?? [];
$max = 1;
foreach ($trend as $t) {
    $max = max($max, (int) $t['users'], (int) $t['posts'], (int) $t['logins']);
}
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.overview')) ?></h1>
    <div class="apx-sub"><?= e(__('app.slogan')) ?></div>
  </div>
  <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/admin/stats')) ?>"><?= e(__('admin.nav.stats')) ?></a>
</div>

<div class="apx-stat-grid apx-stagger">
  <div class="apx-stat">
    <div class="apx-stat__label"><?= e(__('admin.stat.users')) ?></div>
    <div class="apx-stat__value"><?= number_format((int) ($stats['users'] ?? 0)) ?></div>
    <div class="apx-stat__meta">
      <span class="apx-badge apx-badge--success"><?= e(__('admin.stat.active')) ?> <?= (int) ($stats['users_active'] ?? 0) ?></span>
      <span class="apx-badge"><a href="<?= e(route('/admin/users', ['status' => 'pending'])) ?>"><?= e(__('admin.stat.pending')) ?> <?= (int) ($stats['users_pending'] ?? 0) ?></a></span>
      <span class="apx-badge apx-badge--danger"><?= e(__('admin.stat.banned')) ?> <?= (int) ($stats['users_banned'] ?? 0) ?></span>
    </div>
  </div>
  <div class="apx-stat">
    <div class="apx-stat__label"><?= e(__('admin.stat.online')) ?></div>
    <div class="apx-stat__value apx-gradient-text"><?= number_format((int) ($stats['online'] ?? 0)) ?></div>
    <div class="apx-stat__meta"><span class="apx-text-faint"><?= e(__('admin.stat.online_hint')) ?></span></div>
  </div>
  <div class="apx-stat">
    <div class="apx-stat__label"><?= e(__('admin.stat.posts')) ?></div>
    <div class="apx-stat__value"><?= number_format((int) ($stats['posts'] ?? 0)) ?></div>
    <div class="apx-stat__meta">
      <span class="apx-badge"><?= e(__('post.comment')) ?> <?= number_format((int) ($stats['comments'] ?? 0)) ?></span>
      <span class="apx-badge apx-badge--warning"><?= e(__('admin.stat.removed')) ?> <?= (int) ($stats['posts_removed'] ?? 0) ?></span>
    </div>
  </div>
  <div class="apx-stat">
    <div class="apx-stat__label"><?= e(__('nav.groups')) ?></div>
    <div class="apx-stat__value"><?= number_format((int) ($stats['groups'] ?? 0)) ?></div>
    <div class="apx-stat__meta"><span class="apx-badge"><?= e(__('nav.messages')) ?> <?= number_format((int) ($stats['messages'] ?? 0)) ?></span></div>
  </div>
  <div class="apx-stat">
    <div class="apx-stat__label"><?= e(__('admin.nav.reports')) ?></div>
    <div class="apx-stat__value"><?= number_format((int) ($reports['pending'] ?? 0)) ?></div>
    <div class="apx-stat__meta">
      <?php if ((int) ($reports['pending'] ?? 0) > 0): ?>
        <a class="apx-btn apx-btn--danger apx-btn--sm" href="<?= e(route('/admin/reports')) ?>"><?= e(__('admin.report.handle_now')) ?></a>
      <?php else: ?>
        <span class="apx-badge apx-badge--success"><?= e(__('admin.report.all_clear')) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="apx-stat">
    <div class="apx-stat__label">WAF</div>
    <div class="apx-stat__value"><?= number_format((int) ($stats['waf_hits'] ?? 0)) ?></div>
    <div class="apx-stat__meta">
      <span class="apx-badge apx-badge--warning">IP <?= (int) ($stats['ip_bans'] ?? 0) ?></span>
      <a class="apx-badge" href="<?= e(route('/admin/waf')) ?>">WAF →</a>
    </div>
  </div>
</div>

<div class="apx-card apx-card--glass" style="margin-bottom:20px;">
  <div class="apx-card__title"><?= e(__('admin.stat.trend')) ?> · 14<?= e(__('admin.stat.days')) ?></div>
  <div class="apx-chart">
    <?php foreach ($trend as $t): ?>
      <div class="apx-chart__col" title="<?= e($t['date']) ?>: +<?= (int) $t['users'] ?> / <?= (int) $t['posts'] ?> / <?= (int) $t['logins'] ?>">
        <span class="apx-chart__bar apx-chart__bar--users" style="height:<?= (int) round((int) $t['users'] / $max * 100) ?>%"></span>
        <span class="apx-chart__bar apx-chart__bar--posts" style="height:<?= (int) round((int) $t['posts'] / $max * 100) ?>%"></span>
        <span class="apx-chart__bar apx-chart__bar--logins" style="height:<?= (int) round((int) $t['logins'] / $max * 100) ?>%"></span>
        <span class="apx-chart__label"><?= e(substr($t['date'], 8, 2)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="apx-chart__legend">
    <span><i class="apx-dot apx-dot--brand"></i><?= e(__('admin.stat.new_users')) ?></span>
    <span><i class="apx-dot apx-dot--info"></i><?= e(__('admin.stat.new_posts')) ?></span>
    <span><i class="apx-dot apx-dot--success"></i><?= e(__('admin.stat.logins')) ?></span>
  </div>
</div>

<div class="apx-admin-cols">
  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.nav.reports')) ?></div>
    <div class="apx-update-row"><span><?= e(__('admin.report.pending')) ?></span><b><?= (int) ($reports['pending'] ?? 0) ?></b></div>
    <div class="apx-update-row"><span><?= e(__('admin.report.processed')) ?></span><b><?= (int) ($reports['resolved'] ?? 0) ?></b></div>
    <div class="apx-update-row"><span><?= e(__('admin.report.rejected')) ?></span><b><?= (int) ($reports['rejected'] ?? 0) ?></b></div>
    <a class="apx-btn apx-btn--soft apx-btn--sm apx-mt-3" href="<?= e(route('/admin/reports')) ?>"><?= e(__('admin.report.go')) ?></a>
  </div>

  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.nav.quick')) ?></div>
    <div class="apx-quick-grid">
      <a class="apx-quick" href="<?= e(route('/admin/users')) ?>"><?= e(__('admin.nav.users')) ?></a>
      <a class="apx-quick" href="<?= e(route('/admin/posts')) ?>"><?= e(__('admin.nav.posts')) ?></a>
      <a class="apx-quick" href="<?= e(route('/admin/groups')) ?>"><?= e(__('admin.nav.groups')) ?></a>
      <a class="apx-quick" href="<?= e(route('/admin/settings')) ?>"><?= e(__('admin.nav.settings')) ?></a>
      <a class="apx-quick" href="<?= e(route('/admin/logs')) ?>"><?= e(__('admin.nav.logs')) ?></a>
      <a class="apx-quick" href="<?= e(route('/admin/update')) ?>"><?= e(__('admin.nav.update')) ?></a>
    </div>
  </div>
</div>
