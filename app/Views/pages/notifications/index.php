<div class="apx-noti-wrap">
  <div class="apx-noti-head">
    <h1 class="apx-noti-title"><?= e(__('notifications.title')) ?></h1>
    <button class="apx-btn apx-btn--ghost apx-btn--sm" data-mark-all><?= e(__('notifications.mark_all')) ?></button>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="apx-empty-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
      <div><?= e(__('notifications.empty')) ?></div>
    </div>
  <?php else: ?>
    <div class="apx-noti-list">
      <?php foreach ($notifications as $n):
        $unread = empty($n['read_at']);
        $actor = $n['nickname'] ?: ($n['username'] ?? '');
        $bodyKey = (string) ($n['body'] ?? '');
        $extra = '';
        if (strpos($bodyKey, '|') !== false) {
            [$bodyKey, $extra] = explode('|', $bodyKey, 2);
        }
        $text = __($bodyKey, [':actor' => $actor, ':device' => $extra, ':name' => $extra]);
      ?>
      <div class="apx-noti-item<?= $unread ? ' is-unread' : '' ?>">
        <?php if ($unread): ?><span class="apx-noti-dot"></span><?php endif; ?>
        <?= \App\Core\View::partial('avatar', ['user' => $n, 'size' => 'md']) ?>
        <div class="apx-noti-item__body">
          <div class="apx-noti-text"><?= e($text) ?></div>
          <div class="apx-noti-time"><?= e(substr((string)($n['created_at'] ?? ''), 0, 16)) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  var mb = document.querySelector('[data-mark-all]');
  if (mb) mb.addEventListener('click', function () {
    if (window.apx && window.apx.http) {
      window.apx.http.post('/api/notifications/read-all').then(function () {
        document.querySelectorAll('.apx-noti-item.is-unread').forEach(function (el) { el.classList.remove('is-unread'); });
      });
    }
  });
</script>
