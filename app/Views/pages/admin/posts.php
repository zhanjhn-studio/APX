<?php
/** Admin · 内容管理（动态 + 评论） */
$list = $list ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$comments = $comments ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$keyword = $keyword ?? '';
$state = $state ?? '';
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.posts')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.content.subtitle')) ?> · <?= (int) $list['total'] ?> / <?= (int) $comments['total'] ?></div>
  </div>
</div>

<form class="apx-toolbar" method="get" action="<?= e(route('/admin/posts')) ?>">
  <input class="apx-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="<?= e(__('admin.content.search')) ?>" style="max-width:300px;">
  <select class="apx-input" name="state" style="max-width:170px;">
    <option value=""><?= e(__('common.tab.all')) ?></option>
    <option value="visible" <?= $state === 'visible' ? 'selected' : '' ?>><?= e(__('admin.content.visible')) ?></option>
    <option value="removed" <?= $state === 'removed' ? 'selected' : '' ?>><?= e(__('admin.content.removed')) ?></option>
  </select>
  <button class="apx-btn apx-btn--soft apx-btn--sm" type="submit"><?= e(__('search.submit')) ?></button>
  <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/admin/posts')) ?>"><?= e(__('common.action.cancel')) ?></a>
</form>

<div class="apx-card" style="margin-bottom:18px;">
  <div class="apx-card__title"><?= e(__('admin.nav.posts')) ?> · <?= (int) $list['total'] ?></div>
  <div style="overflow:auto;">
    <table class="apx-table">
      <thead><tr>
        <th>#</th><th><?= e(__('admin.content.author')) ?></th><th><?= e(__('admin.content.body')) ?></th>
        <th><?= e(__('post.visibility.label')) ?></th><th>♥/💬</th><th><?= e(__('admin.nav.reports')) ?></th>
        <th><?= e(__('admin.col.created')) ?></th><th><?= e(__('admin.col.actions')) ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($list['items'])): ?>
        <tr><td colspan="8" class="apx-text-faint" style="text-align:center;padding:28px;"><?= e(__('common.empty')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($list['items'] as $p): ?>
        <tr>
          <td class="apx-mono"><?= (int) $p['id'] ?></td>
          <td style="font-size:12px;">@<?= e($p['username']) ?></td>
          <td style="max-width:360px;">
            <div style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= e(mb_substr((string) $p['body'], 0, 200)) ?></div>
          </td>
          <td><span class="apx-badge"><?= e(__('post.visibility.' . $p['visibility'])) ?></span></td>
          <td style="font-size:12px;"><?= (int) $p['like_count'] ?> / <?= (int) $p['comment_count'] ?></td>
          <td><?php if ((int) $p['report_count'] > 0): ?><span class="apx-badge apx-badge--danger"><?= (int) $p['report_count'] ?></span><?php else: ?>—<?php endif; ?></td>
          <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $p['created_at'], 0, 16)) ?></td>
          <td>
            <div class="apx-flex apx-gap-2">
              <a class="apx-icon-btn" href="<?= e(route('/post/' . (int) $p['id'])) ?>" target="_blank" title="<?= e(__('profile.view_profile')) ?>">
                <?= icon('eye', 18) ?>
              </a>
              <?php if ($p['deleted_at'] === null): ?>
                <button class="apx-btn apx-btn--danger apx-btn--sm" data-post-action="remove" data-post="<?= (int) $p['id'] ?>"><?= e(__('post.delete')) ?></button>
              <?php else: ?>
                <button class="apx-btn apx-btn--soft apx-btn--sm" data-post-action="restore" data-post="<?= (int) $p['id'] ?>"><?= e(__('admin.content.restore')) ?></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ((int) $list['pages'] > 1): ?>
  <div class="apx-pager">
    <?php for ($p = 1; $p <= (int) $list['pages']; $p++): ?>
      <a class="apx-pager__item<?= $p === (int) $list['page'] ? ' is-active' : '' ?>" href="<?= e(route('/admin/posts', ['q' => $keyword, 'state' => $state, 'page' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div class="apx-card">
  <div class="apx-card__title"><?= e(__('post.comment')) ?> · <?= (int) $comments['total'] ?></div>
  <div style="overflow:auto;">
    <table class="apx-table">
      <thead><tr>
        <th>#</th><th><?= e(__('admin.content.author')) ?></th><th><?= e(__('admin.content.body')) ?></th>
        <th><?= e(__('admin.content.post_ref')) ?></th><th><?= e(__('admin.col.created')) ?></th><th><?= e(__('admin.col.actions')) ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($comments['items'])): ?>
        <tr><td colspan="6" class="apx-text-faint" style="text-align:center;padding:28px;"><?= e(__('common.empty')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($comments['items'] as $c): ?>
        <tr>
          <td class="apx-mono"><?= (int) $c['id'] ?></td>
          <td style="font-size:12px;">@<?= e($c['username']) ?></td>
          <td style="max-width:340px;font-size:12px;"><?= e(mb_substr((string) $c['body'], 0, 160)) ?></td>
          <td><a class="apx-link" href="<?= e(route('/post/' . (int) $c['post_id'])) ?>" target="_blank">#<?= (int) $c['post_id'] ?></a></td>
          <td class="apx-text-faint" style="font-size:11px;"><?= e(substr((string) $c['created_at'], 0, 16)) ?></td>
          <td>
            <?php if ($c['deleted_at'] === null): ?>
              <button class="apx-btn apx-btn--danger apx-btn--sm" data-comment-action="remove" data-comment="<?= (int) $c['id'] ?>"><?= e(__('post.delete')) ?></button>
            <?php else: ?>
              <button class="apx-btn apx-btn--soft apx-btn--sm" data-comment-action="restore" data-comment="<?= (int) $c['id'] ?>"><?= e(__('admin.content.restore')) ?></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ((int) $comments['pages'] > 1): ?>
  <div class="apx-pager">
    <?php for ($p = 1; $p <= (int) $comments['pages']; $p++): ?>
      <a class="apx-pager__item<?= $p === (int) $comments['page'] ? ' is-active' : '' ?>" href="<?= e(route('/admin/posts', ['q' => $keyword, 'cpage' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
