<?php
use App\Core\View;

$q = $q ?? '';
$overview = $overview ?? ['posts' => [], 'users' => [], 'topics' => [], 'groups' => [], 'count' => 0];
$history = $history ?? [];
$hot = $hot ?? [];
$hasQuery = $q !== '';
?>
<div class="apx-page apx-page--search">
  <div class="apx-main">
    <div class="apx-search-hero">
      <form id="apx-search-form" class="apx-search-box" action="<?= e(route('/search')) ?>" method="get" autocomplete="off">
        <input class="apx-input apx-search-box__input" name="q" id="apx-search-input" value="<?= e($q) ?>"
               placeholder="<?= e(__('search.placeholder')) ?>" autocomplete="off" autofocus>
        <button class="apx-btn apx-btn--primary apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
        <div class="apx-suggest" id="apx-suggest" hidden></div>
      </form>
    </div>

    <?php if (!$hasQuery): ?>
      <?php if (!empty($hot)): ?>
        <section class="apx-card apx-side__block apx-mt-3">
          <div class="apx-side__title"><?= e(__('search.hot')) ?></div>
          <div class="apx-tags apx-mt-2">
            <?php foreach ($hot as $i => $h): ?>
              <a class="apx-pill<?= $i < 3 ? ' apx-pill--solid' : '' ?>"
                 href="<?= e(route('/search?q=' . urlencode($h['keyword']))) ?>">
                <?= $i < 3 ? ($i + 1) . '. ' : '' ?><?= e($h['keyword']) ?>
                <span class="apx-text-faint" style="font-size:11px;margin-left:4px;"><?= (int) $h['times'] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <section class="apx-card apx-side__block apx-mt-3" id="apx-history">
        <div class="apx-side__title apx-flex apx-justify-between">
          <span><?= e(__('search.history')) ?></span>
          <?php if (!empty($history)): ?>
            <button id="apx-clear-history" class="apx-btn apx-btn--ghost apx-btn--sm"><?= e(__('search.clear_history')) ?></button>
          <?php endif; ?>
        </div>
        <?php if (empty($history)): ?>
          <div class="apx-text-faint apx-mt-2" style="font-size:13px;"><?= e(__('search.history_empty')) ?></div>
        <?php else: ?>
          <div class="apx-tags apx-mt-2">
            <?php foreach ($history as $h): ?>
              <a class="apx-pill" href="<?= e(route('/search?q=' . urlencode($h['keyword']))) ?>">#<?= e($h['keyword']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <div class="apx-tabs apx-mt-3">
        <button class="apx-tab is-active" data-tab="posts"><?= e(__('search.tab_posts')) ?></button>
        <button class="apx-tab" data-tab="users"><?= e(__('search.tab_users')) ?></button>
        <button class="apx-tab" data-tab="topics"><?= e(__('search.tab_topics')) ?></button>
        <button class="apx-tab" data-tab="groups"><?= e(__('search.tab_groups')) ?></button>
      </div>

      <div class="apx-search-panel" data-panel="posts">
        <div id="apx-feed" class="apx-feed"></div>
      </div>

      <div class="apx-search-panel" data-panel="users" hidden>
        <div class="apx-card apx-user-list">
          <?php if (empty($overview['users'])): ?>
            <div class="apx-empty__title apx-mt-2"><?= e(__('search.empty')) ?></div>
          <?php else: foreach ($overview['users'] as $u): ?>
            <div class="apx-user-row">
              <a href="<?= e(route('/profile/' . $u['username'])) ?>">
                <?php if (!empty($u['avatar'])): ?>
                  <img class="apx-avatar apx-avatar--md" src="<?= e(asset('uploads/' . ltrim($u['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span class=&quot;apx-avatar apx-avatar--md&quot;><?= e(mb_substr($u['nickname'] ?: $u['username'], 0, 1)) ?></span>'">
                <?php else: ?>
                  <span class="apx-avatar apx-avatar--md"><?= e(mb_substr($u['nickname'] ?: $u['username'], 0, 1)) ?></span>
                <?php endif; ?>
              </a>
              <div class="apx-user-row__body">
                <a class="apx-user-row__name" href="<?= e(route('/profile/' . $u['username'])) ?>"><?= e($u['nickname'] ?: $u['username']) ?></a>
                <div class="apx-user-row__sub">@<?= e($u['username']) ?></div>
              </div>
              <button class="apx-btn apx-btn--soft apx-btn--sm" data-follow="<?= (int) $u['id'] ?>"><?= e(__('post.follow')) ?></button>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="apx-search-panel" data-panel="topics" hidden>
        <div class="apx-card apx-topic-list">
          <?php if (empty($overview['topics'])): ?>
            <div class="apx-empty__title apx-mt-2"><?= e(__('search.empty')) ?></div>
          <?php else: foreach ($overview['topics'] as $tp): ?>
            <a class="apx-topic-row" href="<?= e(route('/topic/' . $tp['slug'])) ?>">
              <span class="apx-link-topic">#<?= e($tp['name']) ?>#</span>
              <span class="apx-text-faint" style="font-size:12px;"><?= (int) $tp['post_count'] ?> <?= e(__('topic.posts')) ?></span>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="apx-search-panel" data-panel="groups" hidden>
        <div class="apx-card apx-group-list">
          <?php if (empty($overview['groups'])): ?>
            <div class="apx-empty__title apx-mt-2"><?= e(__('search.empty')) ?></div>
          <?php else: foreach ($overview['groups'] as $g): ?>
            <div class="apx-user-row" data-group="<?= (int) $g['id'] ?>">
              <span class="apx-side-group__avatar">
                <?php if (!empty($g['avatar'])): ?>
                  <img src="<?= e(asset('uploads/' . ltrim($g['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span><?= e(mb_substr($g['name'], 0, 1)) ?></span>'">
                <?php else: ?>
                  <span><?= e(mb_substr($g['name'], 0, 1)) ?></span>
                <?php endif; ?>
              </span>
              <div class="apx-user-row__body">
                <a class="apx-user-row__name" href="<?= e(route('/group/' . $g['slug'])) ?>"><?= e($g['name']) ?></a>
                <div class="apx-user-row__sub"><?= (int) $g['member_count'] ?> <?= e(__('group.members')) ?> · <?= e(__('group.visibility.' . $g['visibility'])) ?></div>
              </div>
              <?php if (empty($g['is_member'])): ?>
                <button class="apx-btn apx-btn--primary apx-btn--sm" data-group-join="<?= (int) $g['id'] ?>" data-slug="<?= e($g['slug']) ?>"><?= e(__('group.join')) ?></button>
              <?php else: ?>
                <a class="apx-btn apx-btn--soft apx-btn--sm" href="<?= e(route('/group/' . $g['slug'])) ?>"><?= e(__('group.enter')) ?></a>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  window.APX_QUERY = <?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>;
  window.APX_POSTS = <?= json_encode($overview['posts'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= View::scripts([asset('js/modules/search.js'), asset('js/modules/groups.js')]) ?>
