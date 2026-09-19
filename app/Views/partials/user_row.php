<?php
/** Partial: 用户行。 $u 用户数组， $mode = friend | follow。 */
$u = $u ?? [];
$uid = (int) $u['id'];
$name = $u['nickname'] ?: $u['username'];
$isSpecial = !empty($u['is_special']);
$remark = $u['remark'] ?? '';
?>
<div class="apx-user-row" data-user="<?= $uid ?>">
  <?= \App\Core\View::partial('avatar', ['user' => $u, 'size' => 'md']) ?>
  <div class="apx-user-row__body">
    <div class="apx-user-row__name">
      <?= e($name) ?>
      <?php if ($isSpecial): ?><span class="apx-pill apx-pill--special">★</span><?php endif; ?>
    </div>
    <div class="apx-user-row__sub">@<?= e($u['username']) ?><?php if ($remark): ?> · <?= e($remark) ?><?php endif; ?></div>
  </div>
  <div class="apx-user-row__actions">
    <?php if ($mode === 'friend'): ?>
      <button class="apx-btn apx-btn--soft apx-btn--sm" data-special="<?= $uid ?>" data-on="<?= $isSpecial ? 1 : 0 ?>"><?= e(__('friends.special')) ?><?php if ($isSpecial): ?> ✓<?php endif; ?></button>
    <?php endif; ?>
    <button class="apx-btn apx-btn--primary apx-btn--sm" data-message="<?= $uid ?>"><?= e(__('friends.message')) ?></button>
    <?php if ($mode === 'friend'): ?>
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-remove="<?= $uid ?>"><?= e(__('friends.remove')) ?></button>
    <?php endif; ?>
    <?php if ($mode === 'follow'): ?>
      <button class="apx-btn apx-btn--soft apx-btn--sm" data-follow="<?= $uid ?>"><?= e(__('post.follow')) ?></button>
    <?php endif; ?>
  </div>
</div>
