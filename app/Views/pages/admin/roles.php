<?php
/** Admin · 角色与权限 */
$roles = $roles ?? [];
$permissions = $permissions ?? [];
$granted = $granted ?? [];
$current = $current ?? null;
$grantedMap = [];
foreach ($granted as $g) {
    $grantedMap[(int) $g] = true;
}
$grouped = [];
foreach ($permissions as $p) {
    $grouped[(string) $p['group']][] = $p;
}
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.roles')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.role.subtitle')) ?></div>
  </div>
</div>

<div class="apx-admin-cols apx-admin-cols--sidebar">
  <div class="apx-card">
    <div class="apx-card__title"><?= e(__('admin.role.list')) ?></div>
    <?php foreach ($roles as $r): ?>
      <a class="apx-role-item<?= $current && (int) $current['id'] === (int) $r['id'] ? ' is-active' : '' ?>" href="<?= e(route('/admin/roles', ['role' => (int) $r['id']])) ?>">
        <div>
          <div class="apx-role-item__name">
            <?= e($r['name']) ?>
            <?php if ((int) $r['is_system'] === 1): ?><span class="apx-badge"><?= e(__('admin.role.builtin')) ?></span><?php endif; ?>
          </div>
          <div class="apx-role-item__meta"><?= e($r['slug']) ?> · <?= (int) $r['perm_count'] ?> <?= e(__('admin.role.perms')) ?> · <?= (int) $r['user_count'] ?> <?= e(__('admin.role.users')) ?></div>
        </div>
        <?php if ((int) $r['is_system'] === 0): ?>
          <button class="apx-icon-btn" data-role-delete="<?= (int) $r['id'] ?>" title="<?= e(__('common.action.delete')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
          </button>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <form class="apx-role-create" id="apx-role-create">
      <div class="apx-card__title apx-mt-3"><?= e(__('admin.role.create')) ?></div>
      <input class="apx-input" name="name" placeholder="<?= e(__('admin.role.name')) ?>" maxlength="32">
      <input class="apx-input" name="slug" placeholder="<?= e(__('admin.role.slug')) ?>" maxlength="32">
      <input class="apx-input" name="description" placeholder="<?= e(__('admin.role.desc')) ?>" maxlength="128">
      <button class="apx-btn apx-btn--primary apx-btn--sm" type="submit"><?= e(__('common.add')) ?></button>
    </form>
  </div>

  <div class="apx-card">
    <?php if (!$current): ?>
      <div class="apx-empty"><div class="apx-empty__title"><?= e(__('common.empty')) ?></div></div>
    <?php elseif ($current['slug'] === 'super_admin'): ?>
      <div class="apx-card__title"><?= e($current['name']) ?></div>
      <p class="apx-card__hint"><?= e(__('admin.role.super_locked')) ?></p>
    <?php else: ?>
      <div class="apx-card__title">
        <?= e($current['name']) ?>
        <span class="apx-badge apx-badge--primary" style="margin-left:8px;"><?= e($current['slug']) ?></span>
      </div>
      <p class="apx-card__hint"><?= e($current['description'] ?: __('admin.role.no_desc')) ?></p>

      <form id="apx-role-perms" data-role="<?= (int) $current['id'] ?>">
        <?php foreach ($grouped as $group => $items): ?>
          <div class="apx-perm-group">
            <div class="apx-perm-group__title">
              <?= e(__('admin.perm.group.' . $group)) ?>
              <button class="apx-btn apx-btn--link apx-btn--sm" type="button" data-perm-all="<?= e($group) ?>"><?= e(__('common.tab.all')) ?></button>
            </div>
            <div class="apx-perm-grid">
              <?php foreach ($items as $p): ?>
                <label class="apx-perm" data-group="<?= e($group) ?>">
                  <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" <?= isset($grantedMap[(int) $p['id']]) ? 'checked' : '' ?>>
                  <span><?= e($p['name']) ?></span>
                  <code><?= e($p['code']) ?></code>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <button class="apx-btn apx-btn--primary apx-mt-3" type="submit"><?= e(__('common.action.save')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
