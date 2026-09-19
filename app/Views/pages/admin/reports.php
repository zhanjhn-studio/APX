<?php
/** Admin · 举报处理 */
$list = $list ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$counts = $counts ?? ['pending' => 0, 'processing' => 0, 'resolved' => 0, 'rejected' => 0];
$status = $status ?? 'pending';
$type = $type ?? '';
$reasons = $reasons ?? [];
$statusTabs = ['pending' => 'admin.report.pending', 'processing' => 'admin.report.processing', 'resolved' => 'admin.report.processed', 'rejected' => 'admin.report.rejected', '' => 'admin.report.all'];
$typeLabels = ['user' => 'admin.report.type.user', 'post' => 'admin.report.type.post', 'comment' => 'admin.report.type.comment', 'group' => 'admin.report.type.group', 'message' => 'admin.report.type.message'];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.reports')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.report.subtitle')) ?> · <?= (int) $list['total'] ?></div>
  </div>
</div>

<div class="apx-tabs" style="margin-bottom:16px;">
  <?php foreach ($statusTabs as $k => $lk): ?>
    <a class="apx-tab<?= $status === $k ? ' is-active' : '' ?>" href="<?= e(route('/admin/reports', ['status' => $k, 'type' => $type])) ?>">
      <?= e(__($lk)) ?><?php if ($k !== '' && (int) ($counts[$k] ?? 0) > 0): ?><span class="apx-admin-nav__badge"><?= (int) $counts[$k] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<form class="apx-toolbar" method="get" action="<?= e(route('/admin/reports')) ?>">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <select class="apx-input" name="type" style="max-width:200px;">
    <option value=""><?= e(__('admin.report.all_types')) ?></option>
    <?php foreach ($typeLabels as $k => $lk): ?>
      <option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e(__($lk)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
</form>

<?php if (empty($list['items'])): ?>
  <div class="apx-empty"><div class="apx-empty__title"><?= e(__('admin.report.empty')) ?></div></div>
<?php endif; ?>

<?php foreach ($list['items'] as $r):
  // 目标跳转链接（无链接时为空）
  $targetUrl = '';
  if ($r['target_type'] === 'post') {
      $targetUrl = route('/post/' . (int) $r['target_id']);
  } elseif ($r['target_type'] === 'group') {
      $meta = (string) ($r['target']['meta'] ?? '');
      $slug = trim(explode(' · ', $meta)[0], " \t/");
      $targetUrl = $slug !== '' ? route('/group/' . $slug) : '';
  }
?>
  <div class="apx-card apx-report" data-report="<?= (int) $r['id'] ?>">
    <div class="apx-report__head">
      <span class="apx-badge apx-badge--info"><?= e(__($typeLabels[$r['target_type']] ?? 'admin.report.type.post')) ?></span>
      <span class="apx-badge apx-badge--warning"><?= e(__('report.reason.' . $r['reason'])) ?></span>
      <span class="apx-badge <?= $r['status'] === 'pending' ? 'apx-badge--danger' : ($r['status'] === 'resolved' ? 'apx-badge--success' : '') ?>"><?= e(__($statusTabs[$r['status']] ?? 'admin.report.pending')) ?></span>
      <span class="apx-text-faint" style="margin-left:auto;font-size:11px;">#<?= (int) $r['id'] ?> · <?= e(substr((string) $r['created_at'], 0, 16)) ?></span>
    </div>

    <div class="apx-report__body">
      <div class="apx-report__row">
        <span><?= e(__('admin.report.reporter')) ?></span>
        <b>@<?= e($r['reporter_username']) ?></b>
      </div>
      <div class="apx-report__row">
        <span><?= e(__('admin.report.target')) ?></span>
        <b>
          <?php if ($targetUrl !== ''): ?>
            <a class="apx-link" href="<?= e($targetUrl) ?>" target="_blank"><?= e($r['target']['label']) ?></a>
          <?php else: ?>
            <?= e($r['target']['label']) ?>
          <?php endif; ?>
        </b>
      </div>
      <div class="apx-report__row"><span><?= e(__('admin.report.target_meta')) ?></span><b><?= e($r['target']['meta']) ?></b></div>
      <?php if ($r['detail'] !== ''): ?>
        <div class="apx-report__row"><span><?= e(__('admin.report.detail')) ?></span><b><?= e($r['detail']) ?></b></div>
      <?php endif; ?>
    </div>

    <?php if ($r['status'] === 'pending' || $r['status'] === 'processing'): ?>
      <div class="apx-report__actions">
        <input class="apx-input apx-report__note" placeholder="<?= e(__('admin.report.note')) ?>" maxlength="255">
        <button class="apx-btn apx-btn--soft apx-btn--sm" data-report-action="warn" data-report="<?= (int) $r['id'] ?>"><?= e(__('admin.report.warn')) ?></button>
        <button class="apx-btn apx-btn--danger apx-btn--sm" data-report-action="delete" data-report="<?= (int) $r['id'] ?>"><?= e(__('admin.report.delete')) ?></button>
        <button class="apx-btn apx-btn--danger apx-btn--sm" data-report-action="ban" data-report="<?= (int) $r['id'] ?>"><?= e(__('admin.report.ban')) ?></button>
        <button class="apx-btn apx-btn--ghost apx-btn--sm" data-report-action="dismiss" data-report="<?= (int) $r['id'] ?>"><?= e(__('admin.report.dismiss')) ?></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($r['actions'])): ?>
      <div class="apx-report__history">
        <?php foreach ($r['actions'] as $a): ?>
          <div class="apx-report__hist">
            <span class="apx-badge"><?= e(__('report.action.' . $a['action'])) ?></span>
            <span class="apx-text-faint" style="font-size:11px;">@<?= e($a['admin_username'] ?? '—') ?> · <?= e(substr((string) $a['created_at'], 0, 16)) ?></span>
            <?php if ($a['note'] !== ''): ?><span style="font-size:12px;color:var(--text-muted);"><?= e($a['note']) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ((int) $list['pages'] > 1): ?>
<div class="apx-pager">
  <?php for ($p = 1; $p <= (int) $list['pages']; $p++): ?>
    <a class="apx-pager__item<?= $p === (int) $list['page'] ? ' is-active' : '' ?>" href="<?= e(route('/admin/reports', ['status' => $status, 'type' => $type, 'page' => $p])) ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
