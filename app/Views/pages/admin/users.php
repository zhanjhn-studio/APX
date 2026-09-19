<?php
/** Admin · 用户管理 */
$list = $list ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$keyword = $keyword ?? '';
$status = $status ?? '';
$roles = $roles ?? [];
$detail = $detail ?? null;
$statusLabels = ['active' => 'admin.user.status.active', 'pending' => 'admin.user.status.pending', 'banned' => 'admin.user.status.banned', 'deleting' => 'admin.user.status.deleting'];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.users')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.user.subtitle')) ?> · <?= (int) $list['total'] ?></div>
  </div>
</div>

<form class="apx-toolbar" method="get" action="<?= e(route('/admin/users')) ?>">
  <input class="apx-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= e(__('admin.user.search')) ?>" style="max-width:280px;">
  <select class="apx-input" name="status" style="max-width:180px;">
    <option value=""><?= e(__('common.tab.all')) ?></option>
    <?php foreach ($statusLabels as $k => $lk): ?>
      <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e(__($lk)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
</form>

<?php if ($detail): ?>
<div class="apx-card apx-card--glass" style="margin-bottom:18px;">
  <div class="apx-card__title">
    <?= e($detail['user']['nickname'] ?: $detail['user']['username']) ?>
    <a class="apx-btn apx-btn--ghost apx-btn--sm" style="margin-left:auto;" href="<?= e(route('/admin/users')) ?>"><?= e(__('common.action.back')) ?></a>
  </div>
  <div class="apx-detail-grid">
    <div><span><?= e(__('admin.user.col_username')) ?></span><b>@<?= e($detail['user']['username']) ?></b></div>
    <div><span><?= e(__('admin.user.col_email')) ?></span><b><?= e($detail['user']['email']) ?></b></div>
    <div><span><?= e(__('admin.user.col_status')) ?></span><b><?= e(__($statusLabels[$detail['user']['status']] ?? 'admin.user.status.active')) ?></b></div>
    <div><span><?= e(__('level.label', [':level' => (string) $detail['user']['level']])) ?></span><b><?= (int) $detail['user']['experience'] ?> EXP</b></div>
    <div><span><?= e(__('admin.user.col_posts')) ?></span><b><?= (int) $detail['stats']['posts'] ?></b></div>
    <div><span><?= e(__('profile.friends')) ?></span><b><?= (int) $detail['stats']['friends'] ?></b></div>
    <div><span><?= e(__('nav.messages')) ?></span><b><?= (int) $detail['stats']['messages'] ?></b></div>
    <div><span>2FA</span><b><?= (int) $detail['user']['two_factor_enabled'] === 1 ? 'ON' : 'OFF' ?></b></div>
  </div>
  <div class="apx-mt-3 apx-flex apx-gap-2" style="flex-wrap:wrap;">
    <?php foreach ($roles as $r): ?>
      <?php $has = false; foreach ($detail['roles'] as $ur) { if ((int) $ur['id'] === (int) $r['id']) { $has = true; } } ?>
      <button class="apx-btn apx-btn--sm <?= $has ? 'apx-btn--soft' : 'apx-btn--ghost' ?>"
              data-user-action="role_<?= $has ? 'revoke' : 'grant' ?>"
              data-user="<?= (int) $detail['user']['id'] ?>"
              data-role="<?= (int) $r['id'] ?>">
        <?= e($r['name']) ?><?= $has ? ' ✓' : '' ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="apx-card">
  <div style="overflow:auto;">
    <table class="apx-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?= e(__('admin.user.col_username')) ?></th>
          <th><?= e(__('admin.user.col_role')) ?></th>
          <th><?= e(__('admin.user.col_status')) ?></th>
          <th><?= e(__('admin.user.col_posts')) ?></th>
          <th><?= e(__('admin.col.created')) ?></th>
          <th><?= e(__('admin.col.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($list['items'])): ?>
        <tr><td colspan="7" class="apx-text-faint" style="text-align:center;padding:32px;"><?= e(__('common.empty')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($list['items'] as $u): ?>
        <tr>
          <td class="apx-mono"><?= (int) $u['id'] ?></td>
          <td>
            <a class="apx-text-strong" style="font-weight:600;" href="<?= e(route('/admin/users', ['uid' => (int) $u['id']])) ?>"><?= e($u['nickname'] ?: $u['username']) ?></a>
            <div class="apx-text-faint" style="font-size:11px;">@<?= e($u['username']) ?> · <?= e($u['email']) ?></div>
          </td>
          <td style="font-size:12px;">
            <?php if ((int) $u['role_level'] >= 100): ?><span class="apx-badge apx-badge--danger">super</span><?php endif; ?>
            <?= e($u['role_names'] ?? '—') ?>
          </td>
          <td><span class="apx-badge <?= $u['status'] === 'active' ? 'apx-badge--success' : ($u['status'] === 'banned' ? 'apx-badge--danger' : '') ?>"><?= e(__($statusLabels[$u['status']] ?? 'admin.user.status.active')) ?></span></td>
          <td><?= (int) $u['post_count'] ?></td>
          <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $u['created_at'], 0, 10)) ?></td>
          <td>
            <div class="apx-flex apx-gap-2" style="flex-wrap:wrap;">
              <?php if ($u['status'] === 'banned'): ?>
                <button class="apx-btn apx-btn--soft apx-btn--sm" data-user-action="activate" data-user="<?= (int) $u['id'] ?>"><?= e(__('admin.user.unban')) ?></button>
              <?php else: ?>
                <button class="apx-btn apx-btn--danger apx-btn--sm" data-user-action="ban" data-user="<?= (int) $u['id'] ?>"><?= e(__('admin.user.ban')) ?></button>
              <?php endif; ?>
              <?php if ((int) $u['role_level'] >= 100): ?>
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-user-action="revoke_super" data-user="<?= (int) $u['id'] ?>"><?= e(__('admin.user.revoke_super')) ?></button>
              <?php else: ?>
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-user-action="grant_super" data-user="<?= (int) $u['id'] ?>"><?= e(__('admin.user.grant_super')) ?></button>
              <?php endif; ?>
            </div>
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
         href="<?= e(route('/admin/users', ['q' => $keyword, 'status' => $status, 'page' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
