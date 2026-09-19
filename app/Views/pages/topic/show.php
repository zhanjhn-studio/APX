<?php
use App\Core\View;

$topic = $topic ?? null;
?>
<div class="apx-page apx-page--topic">
  <?php if (!$topic): ?>
    <div class="apx-main">
      <div class="apx-card apx-empty">
        <div class="apx-empty__title"><?= e(__('topic.not_found')) ?></div>
      </div>
    </div>
    <?= View::scripts([]) ?>
    <?php return; ?>
  <?php endif; ?>

  <div class="apx-main">
    <section class="apx-card apx-topic-head">
      <div class="apx-topic-head__title">#<?= e($topic['name']) ?>#</div>
      <div class="apx-topic-head__meta">
        <span><b><?= (int) $topic['post_count'] ?></b> <?= e(__('topic.posts')) ?></span>
        <span><b><?= (int) $topic['follower_count'] ?></b> <?= e(__('topic.followers')) ?></span>
      </div>
      <div class="apx-topic-head__actions">
        <button class="apx-btn <?= $following ? 'apx-btn--soft' : 'apx-btn--primary' ?>"
                data-act="topic-follow" data-slug="<?= e($topic['slug']) ?>" data-following="<?= $following ? '1' : '0' ?>">
          <?= e($following ? __('topic.following') : __('topic.follow')) ?>
        </button>
      </div>
    </section>

    <div id="apx-feed" class="apx-feed"></div>
  </div>

  <aside class="apx-side">
    <section class="apx-card apx-side__block">
      <div class="apx-side__title"><?= e(__('topic.about')) ?></div>
      <p class="apx-text-faint apx-mt-2" style="font-size:13px;">
        <?= e(__('topic.trending')) ?> · #<?= e($topic['name']) ?>#
      </p>
    </section>
  </aside>
</div>

<script>
  window.APX_TOPIC = <?= json_encode([
    'slug' => $topic['slug'],
    'name' => $topic['name'],
  ], JSON_UNESCAPED_UNICODE) ?>;
  window.APX_POSTS = <?= json_encode($posts, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= View::scripts([asset('js/modules/topic.js')]) ?>
