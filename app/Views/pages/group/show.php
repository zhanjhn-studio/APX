<?php
/** 群组详情：群动态 / 群聊 / 成员 / 公告 / 管理 五区。 */
use App\Core\LinkRenderer;
use App\Core\View;

$data = $data ?? [];
$group = $data['group'] ?? [];
$role = $data['role'] ?? null;
$perms = $data['permissions'] ?? [];
$isMember = !empty($data['is_member']);
$isPending = !empty($data['is_pending']);
$isMuted = !empty($data['is_muted']);
$posts = $posts ?? [];
$members = $members ?? [];
$announcements = $announcements ?? [];
$requests = $requests ?? [];
$groupJson = [
    'id'          => (int) ($group['id'] ?? 0),
    'slug'        => $group['slug'] ?? '',
    'name'        => $group['name'] ?? '',
    'role'        => $role,
    'is_member'   => $isMember,
    'is_muted'    => $isMuted,
    'permissions' => $perms,
];
?>
<div class="apx-group" id="apx-group"
     data-group="<?= (int) ($group['id'] ?? 0) ?>"
     data-role="<?= e((string) $role) ?>"
     data-muted="<?= $isMuted ? '1' : '0' ?>">

  <section class="apx-card apx-group-head">
    <div class="apx-group-head__glow"></div>
    <div class="apx-group-head__body">
      <span class="apx-group-head__avatar">
        <?php if (!empty($group['avatar'])): ?>
          <img src="<?= e(asset('uploads/' . ltrim($group['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span class=&quot;apx-group-head__initial&quot;><?= e(mb_substr($group['name'] ?? '?', 0, 1)) ?></span>'">
        <?php else: ?>
          <span class="apx-group-head__initial"><?= e(mb_substr($group['name'] ?? '?', 0, 1)) ?></span>
        <?php endif; ?>
      </span>
      <div class="apx-group-head__info">
        <div class="apx-group-head__name">
          <?= e($group['name'] ?? '') ?>
          <span class="apx-group-vis apx-group-vis--<?= e($group['visibility'] ?? 'public') ?>"><?= e(__('group.visibility.' . ($group['visibility'] ?? 'public'))) ?></span>
        </div>
        <div class="apx-group-head__meta">
          <span><?= (int) ($group['member_count'] ?? 0) ?> <?= e(__('group.members')) ?></span>
          <span><?= (int) ($group['post_count'] ?? 0) ?> <?= e(__('group.posts')) ?></span>
          <span><?= e(__('group.owner')) ?>: <?= e($group['owner_nickname'] ?: $group['owner_username'] ?? '') ?></span>
          <?php if ($role !== null): ?>
            <span class="apx-group-role apx-group-role--<?= e($role) ?>"><?= e(__('group.role.' . $role)) ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($group['description'])): ?>
          <p class="apx-group-head__desc"><?= e($group['description']) ?></p>
        <?php endif; ?>
      </div>
      <div class="apx-group-head__actions">
        <?php if (!$isMember): ?>
          <?php if ($isPending): ?>
            <span class="apx-pill"><?= e(__('group.pending')) ?></span>
          <?php elseif (($group['visibility'] ?? '') === 'private'): ?>
            <button class="apx-btn apx-btn--primary apx-btn--sm" data-group-join="<?= (int) $group['id'] ?>"><?= e(__('group.apply')) ?></button>
          <?php else: ?>
            <button class="apx-btn apx-btn--primary apx-btn--sm" data-group-join="<?= (int) $group['id'] ?>"><?= e(__('group.join')) ?></button>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($role !== 'owner'): ?>
            <button class="apx-btn apx-btn--ghost apx-btn--sm" data-group-leave="<?= (int) $group['id'] ?>"><?= e(__('group.leave')) ?></button>
          <?php endif; ?>
          <button class="apx-btn apx-btn--soft apx-btn--sm" data-group-invite-open><?= e(__('group.invite')) ?></button>
        <?php endif; ?>
        <?php if ($role !== 'owner'): ?>
          <button class="apx-btn apx-btn--ghost apx-btn--sm" data-report data-report-type="group" data-report-id="<?= (int) $group['id'] ?>"><?= e(__('report.action')) ?></button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="apx-tabs apx-group-nav" id="apx-group-nav">
    <div class="apx-tab<?= $isMember ? ' is-active' : '' ?>" data-tab="posts"><?= e(__('group.tab.posts')) ?></div>
    <?php if ($isMember): ?>
      <div class="apx-tab" data-tab="chat"><?= e(__('group.tab.chat')) ?></div>
    <?php endif; ?>
    <div class="apx-tab<?= $isMember ? '' : ' is-active' ?>" data-tab="members"><?= e(__('group.tab.members')) ?></div>
    <div class="apx-tab" data-tab="announcements"><?= e(__('group.tab.announcements')) ?></div>
    <?php if (!empty($perms['manage'])): ?>
      <div class="apx-tab" data-tab="manage">
        <?= e(__('group.tab.manage')) ?>
        <?php if (!empty($data['request_count'])): ?><span class="apx-badge apx-badge--danger"><?= (int) $data['request_count'] ?></span><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- 群动态 -->
  <div class="apx-tabpanel<?= $isMember ? ' is-active' : '' ?>" data-panel="posts">
    <?php if (!$isMember): ?>
      <div class="apx-empty">
        <div class="apx-empty__title"><?= e(__('group.join_to_view')) ?></div>
        <button class="apx-btn apx-btn--primary apx-mt-3" data-group-join="<?= (int) $group['id'] ?>"><?= e(__('group.join')) ?></button>
      </div>
    <?php else: ?>
      <?php if (!empty($perms['post'])): ?>
        <div class="apx-card apx-group-compose">
          <textarea class="apx-textarea" id="apx-group-post-input" rows="3" placeholder="<?= e(__('group.post_placeholder')) ?>"></textarea>
          <div class="apx-group-compose__foot">
            <span class="apx-text-faint" style="font-size:12px;"><?= e(__('group.post_visibility_hint')) ?></span>
            <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-group-post-send"><?= e(__('post.send')) ?></button>
          </div>
        </div>
      <?php elseif ($isMuted): ?>
        <div class="apx-card apx-text-muted" style="font-size:13px;"><?= e(__('group.muted_notice')) ?></div>
      <?php endif; ?>

      <div id="apx-group-posts" class="apx-feed" data-total="<?= count($posts) ?>"
           data-cursor="<?= $posts ? (int) $posts[count($posts) - 1]['id'] : 0 ?>">
        <?php if (empty($posts)): ?>
          <div class="apx-empty" id="apx-group-posts-empty">
            <div class="apx-empty__title"><?= e(__('group.posts_empty')) ?></div>
          </div>
        <?php else: foreach ($posts as $p): ?>
          <article class="apx-card apx-gpost" data-post="<?= (int) $p['id'] ?>" data-author="<?= (int) $p['user_id'] ?>">
            <div class="apx-post__head">
              <?= View::partial('avatar', ['user' => $p, 'size' => 'md']) ?>
              <div class="apx-post__meta">
                <span class="apx-post__name"><?= e($p['nickname'] ?: $p['username']) ?></span>
                <span class="apx-post__time">@<?= e($p['username']) ?> · <?= e(substr((string) $p['created_at'], 0, 16)) ?></span>
              </div>
              <?php if ((int) $p['user_id'] === (int) (\App\Services\AuthService::userId() ?? 0) || !empty($perms['manage'])): ?>
                <button class="apx-icon-btn apx-gpost__del" data-gpost-delete="<?= (int) $p['id'] ?>" title="<?= e(__('post.delete')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                </button>
              <?php endif; ?>
            </div>
            <div class="apx-post__body"><?= LinkRenderer::render((string) $p['body']) ?></div>
            <div class="apx-post__actions">
              <button class="apx-post__action<?= !empty($p['liked']) ? ' is-active' : '' ?>" data-gpost-like="<?= (int) $p['id'] ?>" data-liked="<?= !empty($p['liked']) ? 1 : 0 ?>">
                <svg viewBox="0 0 24 24" fill="<?= !empty($p['liked']) ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.5.8-1.3 2-2.5 4-2.5 3.5 0 5 3.5 3.5 6.5C19 16.5 12 21 12 21Z"/></svg>
                <span class="cnt"><?= (int) $p['like_count'] ?></span>
              </button>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
      <?php if (count($posts) >= 20): ?>
        <button class="apx-btn apx-btn--ghost apx-btn--block apx-mt-3" id="apx-group-posts-more"><?= e(__('post.load_more')) ?></button>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- 群聊 -->
  <?php if ($isMember): ?>
  <div class="apx-tabpanel" data-panel="chat">
    <div class="apx-gchat" id="apx-gchat">
      <div class="apx-gchat__body" id="apx-gchat-body">
        <div class="apx-gchat__loading apx-text-faint"><?= e(__('common.status.loading')) ?></div>
      </div>
      <?php if (!empty($perms['chat'])): ?>
        <div class="apx-gchat__compose">
          <textarea class="apx-textarea" id="apx-gchat-input" rows="1" placeholder="<?= e(__('messages.placeholder')) ?>" style="min-height:42px;"></textarea>
          <button class="apx-btn apx-btn--primary" id="apx-gchat-send"><?= e(__('messages.send')) ?></button>
        </div>
      <?php else: ?>
        <div class="apx-gchat__muted apx-text-faint" style="font-size:13px;"><?= e(__('group.muted_notice')) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- 成员 -->
  <div class="apx-tabpanel<?= $isMember ? '' : ' is-active' ?>" data-panel="members">
    <div class="apx-card apx-group-members" id="apx-group-members">
      <?php foreach ($members as $m): ?>
        <div class="apx-user-row" data-user="<?= (int) $m['id'] ?>">
          <?= View::partial('avatar', ['user' => $m, 'size' => 'md']) ?>
          <div class="apx-user-row__body">
            <div class="apx-user-row__name">
              <a href="<?= e(route('/profile/' . $m['username'])) ?>" class="apx-user-row__name"><?= e($m['nickname'] ?: $m['username']) ?></a>
              <span class="apx-group-role apx-group-role--<?= e($m['role']) ?>"><?= e(__('group.role.' . $m['role'])) ?></span>
              <?php if (!empty($m['muted_until']) && strtotime((string) $m['muted_until']) > time()): ?>
                <span class="apx-badge apx-badge--warning"><?= e(__('group.muted_badge')) ?></span>
              <?php endif; ?>
            </div>
            <div class="apx-user-row__sub">@<?= e($m['username']) ?> · <?= e(__('group.joined_at')) ?> <?= e(substr((string) $m['joined_at'], 0, 10)) ?></div>
          </div>
          <?php if (!empty($perms['manage']) && $m['role'] !== 'owner' && (int) $m['id'] !== (int) (\App\Services\AuthService::userId() ?? 0)): ?>
            <div class="apx-user-row__actions">
              <?php if ($role === 'owner' && $m['role'] !== 'admin'): ?>
                <button class="apx-btn apx-btn--soft apx-btn--sm" data-gmember-role="<?= (int) $m['id'] ?>" data-role="admin"><?= e(__('group.make_admin')) ?></button>
              <?php elseif ($role === 'owner' && $m['role'] === 'admin'): ?>
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-gmember-role="<?= (int) $m['id'] ?>" data-role="member"><?= e(__('group.remove_admin')) ?></button>
              <?php endif; ?>
              <?php if ($m['role'] !== 'admin' || $role === 'owner'): ?>
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-gmember-mute="<?= (int) $m['id'] ?>"><?= e(__('group.mute')) ?></button>
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-gmember-kick="<?= (int) $m['id'] ?>"><?= e(__('group.kick')) ?></button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 公告 -->
  <div class="apx-tabpanel" data-panel="announcements">
    <?php if (!empty($perms['announce'])): ?>
      <div class="apx-card apx-group-compose">
        <textarea class="apx-textarea" id="apx-gann-input" rows="2" placeholder="<?= e(__('group.announcement_placeholder')) ?>"></textarea>
        <div class="apx-group-compose__foot">
          <label class="apx-group-pin"><input type="checkbox" id="apx-gann-pin"> <?= e(__('group.pin')) ?></label>
          <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-gann-send"><?= e(__('common.add')) ?></button>
        </div>
      </div>
    <?php endif; ?>
    <div id="apx-gann-list" class="apx-gann-list">
      <?php if (empty($announcements)): ?>
        <div class="apx-empty"><div class="apx-empty__title"><?= e(__('group.announcements_empty')) ?></div></div>
      <?php else: foreach ($announcements as $a): ?>
        <div class="apx-card apx-gann<?= !empty($a['is_pinned']) ? ' is-pinned' : '' ?>" data-ann="<?= (int) $a['id'] ?>">
          <div class="apx-gann__head">
            <?= View::partial('avatar', ['user' => $a, 'size' => 'sm']) ?>
            <span class="apx-gann__name"><?= e($a['nickname'] ?: $a['username']) ?></span>
            <span class="apx-gann__time"><?= e(substr((string) $a['created_at'], 0, 16)) ?></span>
            <?php if (!empty($a['is_pinned'])): ?><span class="apx-badge apx-badge--primary"><?= e(__('group.pinned')) ?></span><?php endif; ?>
          </div>
          <div class="apx-gann__body"><?= nl2br(e($a['body'])) ?></div>
          <?php if (!empty($perms['announce'])): ?>
            <div class="apx-gann__actions">
              <button class="apx-btn apx-btn--ghost apx-btn--sm" data-gann-pin="<?= (int) $a['id'] ?>" data-pinned="<?= !empty($a['is_pinned']) ? 1 : 0 ?>"><?= e(!empty($a['is_pinned']) ? __('group.unpin') : __('group.pin')) ?></button>
              <button class="apx-btn apx-btn--danger apx-btn--sm" data-gann-delete="<?= (int) $a['id'] ?>"><?= e(__('common.action.delete')) ?></button>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- 管理 -->
  <?php if (!empty($perms['manage'])): ?>
  <div class="apx-tabpanel" data-panel="manage">
    <div class="apx-card" style="margin-bottom:16px;">
      <div class="apx-card__title"><?= e(__('group.manage.info')) ?></div>
      <div class="apx-group-form">
        <label class="apx-field">
          <span class="apx-field__label"><?= e(__('group.manage.name')) ?></span>
          <input class="apx-input" id="apx-gset-name" value="<?= e($group['name'] ?? '') ?>" maxlength="64">
        </label>
        <label class="apx-field">
          <span class="apx-field__label"><?= e(__('group.manage.desc')) ?></span>
          <input class="apx-input" id="apx-gset-desc" value="<?= e($group['description'] ?? '') ?>" maxlength="255">
        </label>
        <label class="apx-field">
          <span class="apx-field__label"><?= e(__('group.manage.visibility')) ?></span>
          <select class="apx-input" id="apx-gset-vis">
            <?php foreach (['public', 'private', 'hidden'] as $v): ?>
              <option value="<?= e($v) ?>" <?= ($group['visibility'] ?? '') === $v ? 'selected' : '' ?>><?= e(__('group.visibility.' . $v)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="apx-field">
          <span class="apx-field__label"><?= e(__('group.manage.avatar')) ?></span>
          <input class="apx-input" type="file" id="apx-gset-avatar" accept="image/*">
        </label>
        <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-gset-save"><?= e(__('common.action.save')) ?></button>
      </div>
    </div>

    <div class="apx-card" style="margin-bottom:16px;">
      <div class="apx-card__title"><?= e(__('group.manage.requests')) ?></div>
      <div id="apx-greq-list">
        <?php if (empty($requests)): ?>
          <div class="apx-text-faint" style="font-size:13px;"><?= e(__('group.requests_empty')) ?></div>
        <?php else: foreach ($requests as $r): ?>
          <div class="apx-user-row" data-req="<?= (int) $r['id'] ?>">
            <?= View::partial('avatar', ['user' => $r, 'size' => 'md']) ?>
            <div class="apx-user-row__body">
              <div class="apx-user-row__name"><?= e($r['nickname'] ?: $r['username']) ?></div>
              <div class="apx-user-row__sub">@<?= e($r['username']) ?><?= $r['message'] !== '' ? ' · ' . e($r['message']) : '' ?></div>
            </div>
            <div class="apx-user-row__actions">
              <button class="apx-btn apx-btn--primary apx-btn--sm" data-greq="<?= (int) $r['id'] ?>" data-action="accept"><?= e(__('friends.accept')) ?></button>
              <button class="apx-btn apx-btn--ghost apx-btn--sm" data-greq="<?= (int) $r['id'] ?>" data-action="decline"><?= e(__('friends.decline')) ?></button>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <?php if ($role === 'owner'): ?>
    <div class="apx-card">
      <div class="apx-card__title"><?= e(__('group.manage.danger')) ?></div>
      <div class="apx-group-danger">
        <select class="apx-input" id="apx-gset-owner">
          <option value=""><?= e(__('group.manage.transfer_pick')) ?></option>
          <?php foreach ($members as $m): if ((int) $m['id'] === (int) (\App\Services\AuthService::userId() ?? 0)) continue; ?>
            <option value="<?= (int) $m['id'] ?>"><?= e($m['nickname'] ?: $m['username']) ?> (<?= e(__('group.role.' . $m['role'])) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="apx-btn apx-btn--soft apx-btn--sm" id="apx-gset-transfer"><?= e(__('group.transfer')) ?></button>
        <button class="apx-btn apx-btn--danger apx-btn--sm" id="apx-gset-disband"><?= e(__('group.disband')) ?></button>
      </div>
      <div class="apx-text-faint apx-mt-2" style="font-size:12px;"><?= e(__('group.disband_hint')) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
  window.APX_GROUP = <?= json_encode($groupJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?= View::scripts([asset('js/modules/group.js')]) ?>
