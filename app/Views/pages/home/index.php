<?php
use App\Core\LinkRenderer;
$user = $user ?? [];
$initial = !empty($user['nickname']) ? mb_substr($user['nickname'], 0, 1) : '?';
$unread = (int) ($unread ?? 0);
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('home.welcome', [':name' => $user['nickname'] ?? $user['username'] ?? ''])) ?></h1>
    <div class="apx-sub"><?= e(__('app.slogan')) ?></div>
  </div>
</div>

<div class="apx-feed">
  <div class="apx-feed__col--left">
    <!-- composer -->
    <div class="apx-card apx-post-box">
      <span class="apx-avatar"><?= e($initial) ?></span>
      <form id="apx-composer" class="apx-flex-1" data-no-bind>
        <textarea class="apx-textarea" name="body" id="apx-composer-body" placeholder="<?= e(__('post.placeholder')) ?>" rows="2" style="min-height:46px;"></textarea>
        <div id="apx-composer-media" class="apx-composer__media"></div>
        <div class="apx-flex apx-justify-between apx-mt-2">
          <div class="apx-composer__tools">
            <label class="apx-icon-btn" title="<?= e(__('post.add_image')) ?>">
              <?= icon('image', 20) ?>
              <input type="file" accept="image/*" multiple hidden data-upload="image">
            </label>
            <label class="apx-icon-btn" title="<?= e(__('post.add_video')) ?>">
              <?= icon('video', 20) ?>
              <input type="file" accept="video/*" hidden data-upload="video">
            </label>
            <select class="apx-select" id="apx-composer-vis" title="<?= e(__('post.visibility')) ?>">
              <option value="public"><?= e(__('post.visibility.public')) ?></option>
              <option value="friends"><?= e(__('post.visibility.friends')) ?></option>
              <option value="close_friends"><?= e(__('post.visibility.close')) ?></option>
              <option value="private"><?= e(__('post.visibility.private')) ?></option>
            </select>
          </div>
          <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-composer-btn" type="button">
            <span class="apx-btn-label"><?= e(__('post.send')) ?></span>
          </button>
        </div>
      </form>
    </div>

    <!-- feed -->
    <div id="apx-feed"></div>
  </div>

  <aside class="apx-feed__col--right">
    <div class="apx-card">
      <div class="apx-card__title"><?= e(__('post.recommend_title')) ?></div>
      <?php if (empty($recommended)): ?>
        <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('post.recommend_empty')) ?></div>
      <?php else: ?>
        <?php foreach ($recommended as $r): ?>
          <?php $ri = mb_substr($r['nickname'] ?: $r['username'], 0, 1); $rav = !empty($r['avatar']) ? asset('uploads/' . ltrim($r['avatar'], '/')) : ''; ?>
          <div class="apx-rec-row">
            <?php if ($rav): ?><img class="apx-avatar apx-avatar--sm" src="<?= e($rav) ?>" alt=""><?php else: ?><span class="apx-avatar apx-avatar--sm"><?= e($ri) ?></span><?php endif; ?>
            <div class="apx-flex-1 apx-truncate">
              <div class="apx-rec-row__name"><?= e($r['nickname'] ?: $r['username']) ?></div>
              <div class="apx-rec-row__handle">@<?= e($r['username']) ?></div>
            </div>
            <button class="apx-btn apx-btn--soft apx-btn--sm" data-follow="<?= (int) $r['id'] ?>"><?= e(__('post.follow')) ?></button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="apx-card">
      <div class="apx-card__title"><?= e(__('post.noti_title')) ?></div>
      <?php if (empty($notifications)): ?>
        <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('post.noti_empty')) ?></div>
      <?php else: ?>
        <?php foreach ($notifications as $n): ?>
          <div class="apx-noti-item<?= empty($n['read_at']) ? '' : ' is-read' ?>">
            <span class="apx-noti-dot"></span>
            <div class="apx-flex-1">
              <div class="apx-noti-text"><?= e(__($n['body'] ?? '', [':actor' => $n['nickname'] ?: ($n['username'] ?? '')])) ?></div>
              <div class="apx-noti-time"><?= e(substr((string)($n['created_at'] ?? ''), 0, 16)) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>
</div>

<script>
  window.APX_FEED = <?= json_encode($posts, JSON_UNESCAPED_UNICODE) ?>;
  window.APX_UNREAD = <?= $unread ?>;
</script>
<script type="module" src="<?= asset('js/modules/post.js') ?>"></script>
<script>
  (function () {
    var b = document.getElementById('apx-noti-count');
    if (b && window.APX_UNREAD > 0) { b.textContent = window.APX_UNREAD > 99 ? '99+' : window.APX_UNREAD; b.style.display = ''; }
  })();
</script>
