<?php
$incomingCount = count($incoming ?? []);
?>
<div class="apx-friends">
  <div class="apx-friends__head">
    <h1 class="apx-friends__title"><?= e(__('friends.title')) ?></h1>
  </div>

  <div class="apx-tabs" id="apx-friend-tabs">
    <div class="apx-tab is-active" data-tab="friends"><?= e(__('friends.tab.friends')) ?></div>
    <div class="apx-tab" data-tab="special"><?= e(__('friends.tab.special')) ?></div>
    <div class="apx-tab" data-tab="requests">
      <?= e(__('friends.tab.requests')) ?>
      <?php if ($incomingCount > 0): ?><span class="apx-badge-dot"><?= $incomingCount > 99 ? '99+' : $incomingCount ?></span><?php endif; ?>
    </div>
    <div class="apx-tab" data-tab="followers"><?= e(__('friends.tab.followers')) ?></div>
    <div class="apx-tab" data-tab="following"><?= e(__('friends.tab.following')) ?></div>
  </div>

  <div class="apx-tabpanel is-active" data-panel="friends">
    <?php if (empty($friends)): ?>
      <div class="apx-friends-empty"><?= e(__('friends.empty')) ?></div>
    <?php else: ?>
      <?php foreach ($friends as $f): ?><?= \App\Core\View::partial('user_row', ['u' => $f, 'mode' => 'friend']) ?><?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="apx-tabpanel" data-panel="special">
    <?php if (empty($special)): ?>
      <div class="apx-friends-empty"><?= e(__('friends.special_empty')) ?></div>
    <?php else: ?>
      <?php foreach ($special as $f): ?><?= \App\Core\View::partial('user_row', ['u' => $f, 'mode' => 'friend']) ?><?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="apx-tabpanel" data-panel="requests">
    <?php if (empty($incoming) && empty($outgoing)): ?>
      <div class="apx-friends-empty"><?= e(__('friends.requests_empty')) ?></div>
    <?php else: ?>
      <?php if (!empty($incoming)): ?>
        <div class="apx-text-muted apx-mt-2" style="font-size:13px;"><?= e(__('friends.incoming')) ?></div>
        <?php foreach ($incoming as $r): ?><?= \App\Core\View::partial('request_row', ['r' => $r]) ?><?php endforeach; ?>
      <?php endif; ?>
      <?php if (!empty($outgoing)): ?>
        <div class="apx-text-muted apx-mt-3" style="font-size:13px;"><?= e(__('friends.outgoing')) ?></div>
        <?php foreach ($outgoing as $r): ?>
          <div class="apx-user-row" data-user="<?= (int) $r['user_id'] ?>">
            <?= \App\Core\View::partial('avatar', ['user' => $r, 'size' => 'md']) ?>
            <div class="apx-user-row__body">
              <div class="apx-user-row__name"><?= e($r['nickname'] ?: $r['username']) ?></div>
              <div class="apx-user-row__sub">@<?= e($r['username']) ?></div>
            </div>
            <div class="apx-user-row__actions"><span class="apx-pill"><?= e(__('friends.pending')) ?></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="apx-tabpanel" data-panel="followers">
    <?php if (empty($followers)): ?>
      <div class="apx-friends-empty"><?= e(__('friends.followers_empty')) ?></div>
    <?php else: ?>
      <?php foreach ($followers as $f): ?><?= \App\Core\View::partial('user_row', ['u' => $f, 'mode' => 'follow']) ?><?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="apx-tabpanel" data-panel="following">
    <?php if (empty($following)): ?>
      <div class="apx-friends-empty"><?= e(__('friends.following_empty')) ?></div>
    <?php else: ?>
      <?php foreach ($following as $f): ?><?= \App\Core\View::partial('user_row', ['u' => $f, 'mode' => 'follow']) ?><?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script type="module" src="<?= asset('js/modules/friends.js') ?>"></script>
