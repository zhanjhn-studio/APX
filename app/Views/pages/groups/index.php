<?php
/** 群组广场：我的群组 + 发现群组 */
$mine = $mine ?? [];
$discover = $discover ?? [];
$keyword = $keyword ?? '';
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('group.title')) ?></h1>
    <div class="apx-sub"><?= e(__('group.subtitle')) ?></div>
  </div>
  <button class="apx-btn apx-btn--primary" id="apx-group-create-open">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
    <?= e(__('group.create')) ?>
  </button>
</div>

<form class="apx-group-search" action="<?= e(route('/groups')) ?>" method="get" role="search">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
  <input type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= e(__('group.search_placeholder')) ?>" aria-label="<?= e(__('group.search_placeholder')) ?>">
  <button type="submit" class="apx-btn apx-btn--soft apx-btn--sm"><?= e(__('search.submit')) ?></button>
</form>

<div class="apx-tabs apx-group-tabs" id="apx-group-tabs">
  <div class="apx-tab is-active" data-tab="mine"><?= e(__('group.my')) ?><span class="apx-group-tabcount"><?= count($mine) ?></span></div>
  <div class="apx-tab" data-tab="discover"><?= e(__('group.discover')) ?></div>
</div>

<div class="apx-tabpanel is-active" data-panel="mine">
  <?php if (empty($mine)): ?>
    <div class="apx-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M17 20a6 6 0 0 0-3-5.2"/></svg>
      <div class="apx-empty__title"><?= e(__('group.mine_empty')) ?></div>
      <div><?= e(__('group.mine_empty_hint')) ?></div>
    </div>
  <?php else: ?>
    <div class="apx-group-grid">
      <?php foreach ($mine as $g): ?>
        <a class="apx-group-card apx-card apx-card--hover" href="<?= e(route('/group/' . $g['slug'])) ?>">
          <span class="apx-group-card__avatar">
            <?php if (!empty($g['avatar'])): ?>
              <img src="<?= e(asset('uploads/' . ltrim($g['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span class=&quot;apx-group-card__initial&quot;><?= e(mb_substr($g['name'], 0, 1)) ?></span>'">
            <?php else: ?>
              <span class="apx-group-card__initial"><?= e(mb_substr($g['name'], 0, 1)) ?></span>
            <?php endif; ?>
          </span>
          <div class="apx-group-card__body">
            <div class="apx-group-card__name"><?= e($g['name']) ?></div>
            <div class="apx-group-card__meta">
              <span><?= (int) $g['member_count'] ?> <?= e(__('group.members')) ?></span>
              <span class="apx-group-role apx-group-role--<?= e($g['role']) ?>"><?= e(__('group.role.' . $g['role'])) ?></span>
            </div>
            <div class="apx-group-card__desc"><?= e($g['description'] !== '' ? $g['description'] : __('group.no_desc')) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="apx-tabpanel" data-panel="discover">
  <?php if (empty($discover)): ?>
    <div class="apx-empty">
      <div class="apx-empty__title"><?= e(__('group.discover_empty')) ?></div>
      <div><?= e(__('group.discover_empty_hint')) ?></div>
    </div>
  <?php else: ?>
    <div class="apx-group-grid">
      <?php foreach ($discover as $g): ?>
        <div class="apx-group-card apx-card apx-card--hover" data-group="<?= (int) $g['id'] ?>">
          <span class="apx-group-card__avatar">
            <?php if (!empty($g['avatar'])): ?>
              <img src="<?= e(asset('uploads/' . ltrim($g['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span class=&quot;apx-group-card__initial&quot;><?= e(mb_substr($g['name'], 0, 1)) ?></span>'">
            <?php else: ?>
              <span class="apx-group-card__initial"><?= e(mb_substr($g['name'], 0, 1)) ?></span>
            <?php endif; ?>
          </span>
          <div class="apx-group-card__body">
            <a class="apx-group-card__name" href="<?= e(route('/group/' . $g['slug'])) ?>"><?= e($g['name']) ?></a>
            <div class="apx-group-card__meta">
              <span><?= (int) $g['member_count'] ?> <?= e(__('group.members')) ?></span>
              <span class="apx-group-vis apx-group-vis--<?= e($g['visibility']) ?>"><?= e(__('group.visibility.' . $g['visibility'])) ?></span>
            </div>
            <div class="apx-group-card__desc"><?= e($g['description'] !== '' ? $g['description'] : __('group.no_desc')) ?></div>
          </div>
          <div class="apx-group-card__actions">
            <?php if (!empty($g['is_member'])): ?>
              <a class="apx-btn apx-btn--soft apx-btn--sm" href="<?= e(route('/group/' . $g['slug'])) ?>"><?= e(__('group.enter')) ?></a>
            <?php else: ?>
              <button class="apx-btn apx-btn--primary apx-btn--sm" data-group-join="<?= (int) $g['id'] ?>" data-slug="<?= e($g['slug']) ?>"><?= e(__('group.join')) ?></button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  window.APX_GROUPS = <?= json_encode(['keyword' => $keyword], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= \App\Core\View::scripts([asset('js/modules/groups.js')]) ?>
