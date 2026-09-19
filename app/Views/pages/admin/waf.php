<?php /** Admin · WAF security panel */
$logs = $logs ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$filters = $filters ?? ['action' => '', 'ip' => '', 'min_score' => 0];
$rules = $rules ?? [];
$bans = $bans ?? [];
$mode = $mode ?? 'observe';
$actionClass = ['log' => 'apx-action-pill--log', 'challenge' => 'apx-action-pill--challenge', 'block' => 'apx-action-pill--block', 'ban' => 'apx-action-pill--ban'];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.waf.title')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.waf.subtitle')) ?></div>
  </div>
  <div class="apx-flex apx-gap-2" style="align-items:center;">
    <span class="apx-badge <?= $mode === 'defense' ? 'apx-badge--danger' : 'apx-badge--warning' ?>">
      <?= e($mode === 'defense' ? __('admin.waf.mode_defense') : __('admin.waf.mode_observe')) ?>
    </span>
    <label class="apx-switch" title="<?= e(__('admin.waf.mode_hint')) ?>">
      <input type="checkbox" data-waf-mode <?= $mode === 'defense' ? 'checked' : '' ?>>
      <span></span>
    </label>
  </div>
</div>

<div class="apx-toolbar">
  <form class="apx-search" method="post" action="<?= e(route('/admin/waf/ban')) ?>" data-apx-submit data-reload>
    <?= csrf_field() ?>
    <input class="apx-input" type="text" name="ip" placeholder="<?= e(__('admin.waf.ip')) ?>" required style="max-width:190px;">
    <input class="apx-input" type="text" name="reason" placeholder="<?= e(__('admin.waf.reason')) ?>" style="max-width:200px;">
    <input class="apx-input" type="number" name="days" placeholder="<?= e(__('admin.waf.days')) ?>" min="0" style="max-width:140px;">
    <button class="apx-btn apx-btn--danger apx-btn--sm" type="submit"><?= e(__('admin.waf.ban')) ?></button>
  </form>
</div>

<div class="apx-admin-cols">
  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.waf.rules')) ?> (<?= count($rules) ?>)</div>
    <div class="apx-mt-2" style="max-height:520px;overflow:auto;">
      <table class="apx-table">
        <thead><tr>
          <th><?= e(__('admin.waf.col_rule')) ?></th><th><?= e(__('admin.waf.col_category')) ?></th>
          <th><?= e(__('admin.waf.col_score')) ?></th><th><?= e(__('admin.waf.col_hits')) ?></th>
          <th><?= e(__('admin.waf.col_enabled')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rules as $r): ?>
          <tr>
            <td>
              <div class="apx-text-strong" style="font-weight:600;"><?= e($r['name']) ?></div>
              <div class="apx-mono apx-text-faint" style="font-size:11px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['pattern']) ?></div>
            </td>
            <td><span class="apx-badge"><?= e($r['category']) ?></span></td>
            <td><?= (int) $r['score'] ?></td>
            <td class="apx-text-faint" style="font-size:11px;"><?= number_format((int) $r['hit_count']) ?></td>
            <td>
              <label class="apx-switch">
                <input type="checkbox" data-rule-toggle="<?= (int) $r['id'] ?>" <?= $r['enabled'] ? 'checked' : '' ?>>
                <span></span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.waf.logs')) ?> · <?= (int) $logs['total'] ?></div>

    <form class="apx-toolbar" method="get" action="<?= e(route('/admin/waf')) ?>">
      <input class="apx-input" type="text" name="ip" value="<?= e($filters['ip']) ?>" placeholder="<?= e(__('admin.waf.ip')) ?>" style="max-width:150px;">
      <select class="apx-input" name="action" style="max-width:140px;">
        <option value=""><?= e(__('common.tab.all')) ?></option>
        <?php foreach (['log', 'challenge', 'block', 'ban'] as $a): ?>
          <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
      <input class="apx-input" type="number" name="min_score" value="<?= (int) $filters['min_score'] ?: '' ?>" placeholder="<?= e(__('admin.waf.min_score')) ?>" style="max-width:130px;">
      <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
      <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/admin/waf')) ?>"><?= e(__('common.action.cancel')) ?></a>
    </form>

    <div class="apx-mt-2" style="max-height:420px;overflow:auto;">
      <table class="apx-table apx-log-row">
        <thead><tr>
          <th><?= e(__('admin.waf.ip')) ?></th><th><?= e(__('admin.waf.col_rule')) ?></th>
          <th><?= e(__('admin.waf.col_score')) ?></th><th><?= e(__('admin.waf.col_action')) ?></th>
          <th>URI</th><th><?= e(__('admin.waf.col_time')) ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty($logs['items'])): ?>
          <tr><td colspan="6" class="apx-text-faint" style="text-align:center;padding:24px;"><?= e(__('admin.waf.no_logs')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($logs['items'] as $l): ?>
          <tr>
            <td class="apx-mono"><?= e($l['ip']) ?></td>
            <td class="apx-mono" style="font-size:11px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($l['rule_name'] ?? '') ?></td>
            <td><?= (int) $l['score'] ?></td>
            <td><span class="apx-action-pill <?= $actionClass[$l['action']] ?? 'apx-action-pill--log' ?>"><?= e($l['action']) ?></span></td>
            <td class="apx-mono" style="font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($l['uri'] ?? '') ?></td>
            <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $l['created_at'], 5, 11)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ((int) $logs['pages'] > 1): ?>
    <div class="apx-pager">
      <?php for ($p = 1; $p <= (int) $logs['pages']; $p++): ?>
        <a class="apx-pager__item<?= $p === (int) $logs['page'] ? ' is-active' : '' ?>"
           href="<?= e(route('/admin/waf', ['ip' => $filters['ip'], 'action' => $filters['action'], 'min_score' => (int) $filters['min_score'], 'page' => $p])) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div class="apx-card__title apx-mt-3"><?= e(__('admin.waf.bans_title')) ?> (<?= count($bans) ?>)</div>
    <?php if (empty($bans)): ?>
      <div class="apx-text-faint" style="font-size:13px;"><?= e(__('admin.waf.no_bans')) ?></div>
    <?php endif; ?>
    <?php foreach ($bans as $b): ?>
      <div class="apx-rec-row">
        <span class="apx-mono"><?= e($b['ip'] ?? '') ?></span>
        <span class="apx-text-faint" style="font-size:11px;"><?= e($b['reason']) ?></span>
        <span class="apx-text-faint" style="font-size:11px;">
          <?= $b['expires_at'] === null ? e(__('admin.waf.permanent')) : e(__('admin.waf.expires') . ' ' . substr((string) $b['expires_at'], 5, 11)) ?>
        </span>
        <button class="apx-btn apx-btn--ghost apx-btn--sm" data-unban="<?= e($b['ip'] ?? '') ?>"><?= e(__('admin.waf.unban')) ?></button>
      </div>
    <?php endforeach; ?>
  </div>
</div>
