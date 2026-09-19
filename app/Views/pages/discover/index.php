<?php
use App\Core\View;
$rec_groups = $rec_groups ?? [];
?>
<div class="apx-page apx-page--discover">
  <div class="apx-main">
    <div class="apx-page__head">
      <h1 class="apx-page__title"><?= e(__('discover.title')) ?></h1>
      <p class="apx-text-faint apx-mt-1"><?= e(__('discover.subtitle')) ?></p>
    </div>

    <div class="apx-card apx-feed__title-bar">
      <span class="apx-pill apx-pill--solid"><?= e(__('discover.trending')) ?></span>
    </div>

    <div id="apx-feed" class="apx-feed"></div>
  </div>

  <aside class="apx-side">
    <section class="apx-card apx-side__block">
      <div class="apx-side__title"><?= e(__('discover.recommend')) ?></div>
      <?php if (empty($rec_users)): ?>
        <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('discover.empty')) ?></div>
      <?php else: foreach ($rec_users as $u): ?>
        <div class="apx-user-row">
          <?php if (!empty($u['avatar'])): ?>
            <img class="apx-avatar apx-avatar--sm" src="<?= e(asset('uploads/' . ltrim($u['avatar'], '/'))) ?>" alt="">
          <?php else: ?>
            <span class="apx-avatar apx-avatar--sm"><?= e(mb_substr($u['nickname'] ?: $u['username'], 0, 1)) ?></span>
          <?php endif; ?>
          <div class="apx-user-row__meta">
            <a class="apx-user-row__name" href="<?= e(route('/profile/' . $u['username'])) ?>"><?= e($u['nickname'] ?: $u['username']) ?></a>
            <div class="apx-user-row__handle">@<?= e($u['username']) ?></div>
          </div>
          <button class="apx-btn apx-btn--soft apx-btn--sm" data-follow="<?= (int) $u['id'] ?>"><?= e(__('post.follow')) ?></button>
        </div>
      <?php endforeach; endif; ?>
    </section>

    <section class="apx-card apx-side__block">
      <div class="apx-side__title">
        <?= e(__('discover.recommend_groups')) ?>
        <a class="apx-btn apx-btn--link apx-btn--sm" href="<?= e(route('/groups')) ?>" style="margin-left:auto;"><?= e(__('topic.view_all')) ?></a>
      </div>
      <?php if (empty($rec_groups)): ?>
        <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('discover.empty')) ?></div>
      <?php else: foreach ($rec_groups as $g): ?>
        <div class="apx-user-row" data-group="<?= (int) $g['id'] ?>">
          <span class="apx-side-group__avatar">
            <?php if (!empty($g['avatar'])): ?>
              <img src="<?= e(asset('uploads/' . ltrim($g['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span><?= e(mb_substr($g['name'], 0, 1)) ?></span>'">
            <?php else: ?>
              <span><?= e(mb_substr($g['name'], 0, 1)) ?></span>
            <?php endif; ?>
          </span>
          <div class="apx-user-row__meta">
            <a class="apx-user-row__name" href="<?= e(route('/group/' . $g['slug'])) ?>"><?= e($g['name']) ?></a>
            <div class="apx-user-row__handle"><?= (int) $g['member_count'] ?> <?= e(__('group.members')) ?></div>
          </div>
          <button class="apx-btn apx-btn--soft apx-btn--sm" data-group-join="<?= (int) $g['id'] ?>" data-slug="<?= e($g['slug']) ?>"><?= e(__('group.join')) ?></button>
        </div>
      <?php endforeach; endif; ?>
    </section>

    <section class="apx-card apx-side__block">
      <div class="apx-side__title"><?= e(__('discover.hot_topics')) ?></div>
      <?php if (empty($hot_topics)): ?>
        <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('common.empty')) ?></div>
      <?php else: foreach ($hot_topics as $tp): ?>
        <a class="apx-topic-row" href="<?= e(route('/topic/' . $tp['slug'])) ?>">
          <span class="apx-link-topic">#<?= e($tp['name']) ?>#</span>
          <span class="apx-text-faint apx-mt-1" style="font-size:12px;"><?= (int) $tp['post_count'] ?> <?= e(__('topic.posts')) ?></span>
        </a>
      <?php endforeach; endif; ?>
    </section>
  </aside>
</div>

<script>
  window.APX_POSTS = <?= json_encode($hot_posts, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= View::scripts([asset('js/modules/discover.js'), asset('js/modules/groups.js')]) ?>
