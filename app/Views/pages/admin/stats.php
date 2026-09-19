<?php
/** Admin · 数据统计 */
$stats = $stats ?? [];
$trend = $trend ?? [];
$boards = $boards ?? [];
$max = 1;
foreach ($trend as $t) {
    $max = max($max, (int) $t['users'], (int) $t['posts'], (int) $t['logins'], (int) $t['messages']);
}
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.stats')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.stat.subtitle')) ?></div>
  </div>
</div>

<div class="apx-stat-grid apx-stagger">
  <div class="apx-stat"><div class="apx-stat__label"><?= e(__('admin.stat.users')) ?></div><div class="apx-stat__value"><?= number_format((int) ($stats['users'] ?? 0)) ?></div></div>
  <div class="apx-stat"><div class="apx-stat__label"><?= e(__('admin.stat.posts')) ?></div><div class="apx-stat__value"><?= number_format((int) ($stats['posts'] ?? 0)) ?></div></div>
  <div class="apx-stat"><div class="apx-stat__label"><?= e(__('post.comment')) ?></div><div class="apx-stat__value"><?= number_format((int) ($stats['comments'] ?? 0)) ?></div></div>
  <div class="apx-stat"><div class="apx-stat__label"><?= e(__('nav.messages')) ?></div><div class="apx-stat__value"><?= number_format((int) ($stats['messages'] ?? 0)) ?></div></div>
  <div class="apx-stat"><div class="apx-stat__label"><?= e(__('nav.groups')) ?></div><div class="apx-stat__value"><?= number_format((int) ($stats['groups'] ?? 0)) ?></div></div>
  <div class="apx-stat"><div class="apx-stat__label"><?= e(__('admin.stat.online')) ?></div><div class="apx-stat__value apx-gradient-text"><?= number_format((int) ($stats['online'] ?? 0)) ?></div></div>
</div>

<div class="apx-card apx-card--glass" style="margin-bottom:20px;">
  <div class="apx-card__title"><?= e(__('admin.stat.trend')) ?> · 30<?= e(__('admin.stat.days')) ?></div>
  <div class="apx-chart apx-chart--wide">
    <?php foreach ($trend as $t): ?>
      <div class="apx-chart__col" title="<?= e($t['date']) ?>: +<?= (int) $t['users'] ?> / <?= (int) $t['posts'] ?> / <?= (int) $t['messages'] ?>">
        <span class="apx-chart__bar apx-chart__bar--users" style="height:<?= (int) round((int) $t['users'] / $max * 100) ?>%"></span>
        <span class="apx-chart__bar apx-chart__bar--posts" style="height:<?= (int) round((int) $t['posts'] / $max * 100) ?>%"></span>
        <span class="apx-chart__bar apx-chart__bar--success" style="height:<?= (int) round((int) $t['messages'] / $max * 100) ?>%"></span>
        <span class="apx-chart__label"><?= e(substr($t['date'], 8, 2)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="apx-chart__legend">
    <span><i class="apx-dot apx-dot--brand"></i><?= e(__('admin.stat.new_users')) ?></span>
    <span><i class="apx-dot apx-dot--info"></i><?= e(__('admin.stat.new_posts')) ?></span>
    <span><i class="apx-dot apx-dot--success"></i><?= e(__('nav.messages')) ?></span>
  </div>
</div>

<div class="apx-admin-cols">
  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.stat.active_users')) ?></div>
    <?php foreach (($boards['active_users'] ?? []) as $u): ?>
      <div class="apx-user-row" data-user="<?= (int) $u['id'] ?>">
        <?= \App\Core\View::partial('avatar', ['user' => $u, 'size' => 'sm']) ?>
        <div class="apx-user-row__body">
          <a class="apx-user-row__name" href="<?= e(route('/admin/users', ['uid' => (int) $u['id']])) ?>"><?= e($u['nickname'] ?: $u['username']) ?></a>
          <div class="apx-user-row__sub">@<?= e($u['username']) ?> · Lv.<?= (int) $u['experience'] ?></div>
        </div>
        <span class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) ($u['last_active_at'] ?? ''), 5, 11)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.stat.top_authors')) ?></div>
    <?php foreach (($boards['top_authors'] ?? []) as $u): ?>
      <div class="apx-update-row">
        <span>@<?= e($u['username']) ?></span>
        <b><?= (int) $u['post_count'] ?> · ♥<?= (int) $u['likes'] ?></b>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('discover.hot_topics')) ?></div>
    <?php foreach (($boards['hot_topics'] ?? []) as $t): ?>
      <div class="apx-update-row">
        <span>#<?= e($t['name']) ?>#</span>
        <b><?= (int) $t['post_count'] ?></b>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.stat.big_groups')) ?></div>
    <?php foreach (($boards['big_groups'] ?? []) as $g): ?>
      <div class="apx-update-row">
        <span><?= e($g['name']) ?></span>
        <b><?= (int) $g['member_count'] ?> / <?= (int) $g['post_count'] ?></b>
      </div>
    <?php endforeach; ?>
  </div>
</div>
