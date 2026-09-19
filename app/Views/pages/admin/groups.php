<?php
/** Admin · 群组管理 */
$list = $list ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$keyword = $keyword ?? '';
$state = $state ?? 'active';
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.groups')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.group.subtitle')) ?> · <?= (int) $list['total'] ?></div>
  </div>
</div>

<form class="apx-toolbar" method="get" action="<?= e(route('/admin/groups')) ?>">
  <input class="apx-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= e(__('admin.group.search')) ?>" style="max-width:280px;">
  <select class="apx-input" name="state" style="max-width:180px;">
    <option value="active" <?= $state === 'active' ? 'selected' : '' ?>><?= e(__('admin.group.state_active')) ?></option>
    <option value="disbanded" <?= $state === 'disbanded' ? 'selected' : '' ?>><?= e(__('admin.group.state_disbanded')) ?></option>
    <option value="" <?= $state === '' ? 'selected' : '' ?>><?= e(__('common.tab.all')) ?></option>
  </select>
  <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
</form>

<div class="apx-card">
  <div style="overflow:auto;">
    <table class="apx-table">
      <thead><tr>
        <th>#</th><th><?= e(__('admin.group.col_name')) ?></th><th><?= e(__('group.owner')) ?></th>
        <th><?= e(__('group.manage.visibility')) ?></th><th><?= e(__('group.members')) ?></th>
        <th><?= e(__('group.posts')) ?></th><th><?= e(__('admin.col.status')) ?></th><th><?= e(__('admin.col.actions')) ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($list['items'])): ?>
        <tr><td colspan="8" class="apx-text-faint" style="text-align:center;padding:32px;"><?= e(__('common.empty')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($list['items'] as $g): ?>
        <tr>
          <td class="apx-mono"><?= (int) $g['id'] ?></td>
          <td>
            <a class="apx-text-strong" style="font-weight:600;" href="<?= e(route('/group/' . $g['slug'])) ?>" target="_blank"><?= e($g['name']) ?></a>
            <div class="apx-text-faint" style="font-size:11px;">/<?= e($g['slug']) ?> · <?= e(substr((string) $g['created_at'], 0, 10)) ?></div>
          </td>
          <td style="font-size:12px;"><?= e($g['owner_nickname'] ?: $g['owner_username']) ?></td>
          <td><span class="apx-badge"><?= e(__('group.visibility.' . $g['visibility'])) ?></span></td>
          <td><?= (int) $g['member_count'] ?></td>
          <td><?= (int) $g['post_count'] ?></td>
          <td><span class="apx-badge <?= $g['status'] === 'active' ? 'apx-badge--success' : 'apx-badge--danger' ?>"><?= e($g['status']) ?></span></td>
          <td>
            <?php if ($g['status'] === 'active'): ?>
              <button class="apx-btn apx-btn--danger apx-btn--sm" data-group-action="disband" data-group="<?= (int) $g['id'] ?>"><?= e(__('group.disband')) ?></button>
            <?php else: ?>
              <button class="apx-btn apx-btn--soft apx-btn--sm" data-group-action="restore" data-group="<?= (int) $g['id'] ?>"><?= e(__('admin.group.restore')) ?></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ((int) $list['pages'] > 1): ?>
  <div class="apx-pager">
    <?php for ($p = 1; $p <= (int) $list['pages']; $p++): ?>
      <a class="apx-pager__item<?= $p === (int) $list['page'] ? ' is-active' : '' ?>" href="<?= e(route('/admin/groups', ['q' => $keyword, 'state' => $state, 'page' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
