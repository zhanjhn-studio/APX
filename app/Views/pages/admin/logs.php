<?php
/** Admin · 日志（管理员操作 / 登录） */
$tab = $tab ?? 'admin';
$keyword = $keyword ?? '';
$result = $result ?? '';
$admin = $admin ?? null;
$login = $login ?? null;
$current = $tab === 'admin' ? $admin : $login;
$resultLabels = [
    'success' => 'admin.log.result.success', 'fail' => 'admin.log.result.fail',
    'locked' => 'admin.log.result.locked', 'totp_required' => 'admin.log.result.totp_required',
    'totp_fail' => 'admin.log.result.totp_fail',
];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.logs')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.log.subtitle')) ?></div>
  </div>
</div>

<div class="apx-tabs" style="margin-bottom:16px;">
  <a class="apx-tab<?= $tab === 'admin' ? ' is-active' : '' ?>" href="<?= e(route('/admin/logs', ['tab' => 'admin'])) ?>"><?= e(__('admin.log.tab_admin')) ?></a>
  <a class="apx-tab<?= $tab === 'login' ? ' is-active' : '' ?>" href="<?= e(route('/admin/logs', ['tab' => 'login'])) ?>"><?= e(__('admin.log.tab_login')) ?></a>
</div>

<form class="apx-toolbar" method="get" action="<?= e(route('/admin/logs')) ?>">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php if ($tab === 'admin'): ?>
    <input class="apx-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= e(__('admin.log.search_action')) ?>" style="max-width:300px;">
  <?php else: ?>
    <input class="apx-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="IP" style="max-width:200px;">
    <select class="apx-input" name="result" style="max-width:200px;">
      <option value=""><?= e(__('common.tab.all')) ?></option>
      <?php foreach ($resultLabels as $k => $lk): ?>
        <option value="<?= e($k) ?>" <?= $result === $k ? 'selected' : '' ?>><?= e(__($lk)) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
</form>

<div class="apx-card">
  <div style="overflow:auto;">
    <table class="apx-table apx-log-row">
      <?php if ($tab === 'admin'): ?>
        <thead><tr>
          <th>#</th><th><?= e(__('admin.log.admin')) ?></th><th><?= e(__('admin.log.action')) ?></th>
          <th><?= e(__('admin.log.target')) ?></th><th><?= e(__('admin.log.detail')) ?></th>
          <th>IP</th><th><?= e(__('admin.col.time')) ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty(($current['items'] ?? []))): ?>
          <tr><td colspan="7" class="apx-text-faint" style="text-align:center;padding:32px;"><?= e(__('admin.log.empty')) ?></td></tr>
        <?php endif; ?>
        <?php foreach (($current['items'] ?? []) as $l): ?>
          <tr>
            <td class="apx-mono"><?= (int) $l['id'] ?></td>
            <td style="font-size:12px;"><?= e($l['nickname'] ?: ($l['username'] ?? '—')) ?></td>
            <td><span class="apx-badge apx-badge--primary"><?= e($l['action']) ?></span></td>
            <td class="apx-mono" style="font-size:11px;"><?= e($l['target_type']) ?><?= $l['target_id'] !== null ? '#' . (int) $l['target_id'] : '' ?></td>
            <td style="max-width:260px;font-size:12px;color:var(--text-muted);"><?= e($l['detail']) ?></td>
            <td class="apx-mono" style="font-size:11px;"><?= e($l['ip']) ?></td>
            <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $l['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      <?php else: ?>
        <thead><tr>
          <th>#</th><th><?= e(__('admin.log.user')) ?></th><th>IP</th>
          <th><?= e(__('admin.log.result')) ?></th><th><?= e(__('admin.log.reason')) ?></th><th><?= e(__('admin.col.time')) ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty(($current['items'] ?? []))): ?>
          <tr><td colspan="6" class="apx-text-faint" style="text-align:center;padding:32px;"><?= e(__('admin.log.empty')) ?></td></tr>
        <?php endif; ?>
        <?php foreach (($current['items'] ?? []) as $l): ?>
          <tr>
            <td class="apx-mono"><?= (int) $l['id'] ?></td>
            <td style="font-size:12px;"><?= $l['username'] ? '@' . e($l['username']) : '—' ?></td>
            <td class="apx-mono" style="font-size:11px;"><?= e($l['ip']) ?></td>
            <td>
              <span class="apx-badge <?= $l['result'] === 'success' ? 'apx-badge--success' : 'apx-badge--danger' ?>"><?= e(__($resultLabels[$l['result']] ?? 'admin.log.result.fail')) ?></span>
            </td>
            <td style="font-size:12px;color:var(--text-muted);"><?= e($l['reason'] ?? '') ?></td>
            <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $l['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      <?php endif; ?>
    </table>
  </div>

  <?php $pages = (int) ($current['pages'] ?? 1); $page = (int) ($current['page'] ?? 1); ?>
  <?php if ($pages > 1): ?>
  <div class="apx-pager">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a class="apx-pager__item<?= $p === $page ? ' is-active' : '' ?>"
         href="<?= e(route('/admin/logs', ['tab' => $tab, 'q' => $keyword, 'result' => $result, 'page' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
