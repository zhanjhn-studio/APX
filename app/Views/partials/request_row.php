<?php
/** Partial: 好友申请行。 $r 含 id(申请id), user_id(申请人), username, nickname, avatar, message。 */
$r = $r ?? [];
$reqId = (int) $r['id'];
$userId = (int) $r['user_id'];
$name = $r['nickname'] ?: $r['username'];
?>
<div class="apx-user-row" data-request="<?= $reqId ?>" data-user="<?= $userId ?>">
  <?= \App\Core\View::partial('avatar', ['user' => $r, 'size' => 'md']) ?>
  <div class="apx-user-row__body">
    <div class="apx-user-row__name"><?= e($name) ?></div>
    <div class="apx-user-row__sub">@<?= e($r['username']) ?></div>
    <?php if (!empty($r['message'])): ?><div class="apx-user-row__msg"><?= e($r['message']) ?></div><?php endif; ?>
  </div>
  <div class="apx-user-row__actions">
    <button class="apx-btn apx-btn--primary apx-btn--sm" data-accept="<?= $reqId ?>"><?= e(__('friends.accept')) ?></button>
    <button class="apx-btn apx-btn--ghost apx-btn--sm" data-decline="<?= $reqId ?>"><?= e(__('friends.decline')) ?></button>
  </div>
</div>
