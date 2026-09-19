<?php
/** Admin · 黑名单（用户之间的拉黑关系） */
$list = $list ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$keyword = $keyword ?? '';
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.blocked')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.blocked.subtitle')) ?> · <?= (int) $list['total'] ?></div>
  </div>
</div>

<form class="apx-toolbar" method="get" action="<?= e(route('/admin/blocked')) ?>">
  <input class="apx-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= e(__('admin.blocked.search')) ?>" style="max-width:280px;">
  <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
  <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/admin/blocked')) ?>"><?= e(__('common.action.cancel')) ?></a>
</form>

<div class="apx-card">
  <div style="overflow:auto;">
    <table class="apx-table">
      <thead><tr>
        <th>#</th>
        <th><?= e(__('admin.blocked.col_user')) ?></th>
        <th><?= e(__('admin.blocked.col_target')) ?></th>
        <th><?= e(__('admin.col.created')) ?></th>
        <th><?= e(__('admin.col.actions')) ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($list['items'])): ?>
        <tr><td colspan="5" class="apx-text-faint" style="text-align:center;padding:32px;"><?= e(__('admin.blocked.empty')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($list['items'] as $b): ?>
        <tr>
          <td class="apx-mono"><?= (int) $b['id'] ?></td>
          <td>
            <a class="apx-text-strong" style="font-weight:600;" href="<?= e(route('/admin/users', ['uid' => (int) $b['user_id']])) ?>">
              <?= e($b['nickname'] ?: $b['username']) ?>
            </a>
            <div class="apx-text-faint" style="font-size:11px;">@<?= e($b['username']) ?></div>
          </td>
          <td>
            <a class="apx-text-strong" style="font-weight:600;" href="<?= e(route('/admin/users', ['uid' => (int) $b['target_id']])) ?>">
              <?= e($b['target_nickname'] ?: $b['target_username']) ?>
            </a>
            <div class="apx-text-faint" style="font-size:11px;">@<?= e($b['target_username']) ?></div>
          </td>
          <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $b['created_at'], 0, 16)) ?></td>
          <td>
            <button class="apx-btn apx-btn--danger apx-btn--sm" data-block-remove="<?= (int) $b['id'] ?>"><?= e(__('admin.blocked.remove')) ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ((int) $list['pages'] > 1): ?>
  <div class="apx-pager">
    <?php for ($p = 1; $p <= (int) $list['pages']; $p++): ?>
      <a class="apx-pager__item<?= $p === (int) $list['page'] ? ' is-active' : '' ?>"
         href="<?= e(route('/admin/blocked', ['q' => $keyword, 'page' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
