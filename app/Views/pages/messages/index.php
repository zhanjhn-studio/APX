<?php
/** 私聊 / 群聊：会话列表 + 会话区（引用回复、搜索、附件、置顶/免打扰/删除） */
$uid = (int) ($uid ?? 0);
$open_id = (int) ($open_id ?? 0);
$conversations = $conversations ?? [];
?>
<div class="apx-chat" id="apx-chat" data-uid="<?= $uid ?>" data-open="<?= (int) $open_id ?>">
  <div class="apx-chat__list">
    <div class="apx-chat__list-head">
      <input class="apx-input" id="apx-conv-search" placeholder="<?= e(__('messages.search')) ?>" style="height:38px;flex:1;">
      <button class="apx-icon-btn" id="apx-new-chat" title="<?= e(__('messages.start')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      </button>
    </div>
    <div id="apx-conv-items">
      <?php if (empty($conversations)): ?>
        <div class="apx-chat__list-empty apx-text-faint apx-mt-4" style="text-align:center;padding:32px 0;font-size:13px;">
          <?= e(__('messages.empty')) ?>
        </div>
      <?php else: ?>
        <?php foreach ($conversations as $c):
          $cid = (int) $c['conversation_id'];
          $isGroup = $c['type'] === 'group';
          $title = $isGroup ? ($c['title'] ?: __('messages.group')) : ($c['peer_nickname'] ?: $c['peer_username'] ?: __('messages.chat'));
          $avatar = $isGroup ? '' : ($c['peer_avatar'] ?? '');
          $lastType = $c['last_type'] ?? '';
          if ($lastType === 'recall') { $preview = __('messages.preview_recall'); }
          elseif (in_array($lastType, ['image', 'file', 'emoji', 'location', 'system'], true)) { $preview = __('messages.type.' . $lastType); }
          else { $preview = $c['last_body'] ?? ''; }
          if ($c['last_sender'] && (int) $c['last_sender'] === $uid && $preview !== '') { $preview = __('messages.you') . ': ' . $preview; }
          $unread = (int) $c['unread_count'];
          $pinned = (int) $c['is_pinned'] === 1;
          $muted = (int) $c['is_muted'] === 1;
        ?>
        <div class="apx-chat__item<?= $pinned ? ' is-pinned' : '' ?>" data-conv="<?= $cid ?>"
             data-type="<?= e($c['type']) ?>" data-pinned="<?= $pinned ? 1 : 0 ?>" data-muted="<?= $muted ? 1 : 0 ?>">
          <a class="apx-chat__open" href="?c=<?= $cid ?>">
            <?php if ($avatar): ?>
              <img class="apx-avatar" src="<?= e(asset('uploads/' . ltrim($avatar, '/'))) ?>" alt="" onerror="this.outerHTML='<span class=&quot;apx-avatar&quot;><?= e(mb_substr($title, 0, 1)) ?></span>'">
            <?php else: ?>
              <span class="apx-avatar"><?= e(mb_substr($title, 0, 1)) ?></span>
            <?php endif; ?>
            <div class="apx-chat__item-body">
              <div class="apx-chat__item-name">
                <span class="apx-truncate">
                  <?php if ($pinned): ?><span class="apx-chat__flag" title="<?= e(__('messages.pin')) ?>">↑</span><?php endif; ?>
                  <?php if ($muted): ?><span class="apx-chat__flag" title="<?= e(__('messages.mute')) ?>">🔇</span><?php endif; ?>
                  <?= e($title) ?>
                </span>
                <?php if ($unread > 0): ?><span class="apx-chat__unread"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
              </div>
              <div class="apx-chat__item-prev"><?= e($preview) ?></div>
            </div>
          </a>
          <button class="apx-chat__more" data-conv-menu="<?= $cid ?>" title="<?= e(__('messages.conv_actions')) ?>">⋯</button>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="apx-thread" id="apx-thread">
    <div class="apx-thread__empty" id="apx-thread-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" style="width:54px;height:54px;opacity:.35;margin-bottom:12px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>
      <div><?= e(__('messages.no_conversation')) ?></div>
    </div>

    <div class="apx-thread__head" id="apx-thread-head" hidden>
      <div id="apx-thread-avatar-wrap" class="apx-avatar apx-avatar--sm"></div>
      <div class="apx-flex-1">
        <div id="apx-thread-name"></div>
        <div class="apx-thread__typing" id="apx-thread-typing" hidden></div>
      </div>
      <button class="apx-icon-btn" id="apx-thread-search" title="<?= e(__('messages.search_in')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </button>
      <button class="apx-icon-btn" id="apx-thread-info" title="<?= e(__('messages.thread_info')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg></button>
    </div>

    <div class="apx-thread__search" id="apx-thread-searchbar" hidden>
      <input class="apx-input" id="apx-thread-search-input" placeholder="<?= e(__('messages.search_placeholder')) ?>">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" id="apx-thread-search-close"><?= e(__('common.cancel')) ?></button>
    </div>
    <div class="apx-chat-search" id="apx-thread-search-results" hidden></div>

    <div class="apx-thread__body" id="apx-thread-body" hidden></div>

    <div class="apx-reply-bar" id="apx-reply-bar" hidden>
      <span class="apx-reply-bar__label"><?= e(__('messages.replying_to')) ?></span>
      <span class="apx-reply-bar__text" id="apx-reply-text"></span>
      <button class="apx-icon-btn" id="apx-reply-cancel" title="<?= e(__('messages.cancel_reply')) ?>">✕</button>
    </div>

    <div class="apx-thread__compose" id="apx-thread-compose" hidden>
      <div class="apx-thread__tools">
        <button class="apx-icon-btn" id="apx-emoji-btn" title="<?= e(__('messages.emoji')) ?>">😊</button>
        <button class="apx-icon-btn" id="apx-image-btn" title="<?= e(__('messages.attach_image')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg>
        </button>
        <button class="apx-icon-btn" id="apx-file-btn" title="<?= e(__('messages.attach_file')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.5 12.5 21a5 5 0 0 1-7-7l8-8a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7-7"/></svg>
        </button>
        <button class="apx-icon-btn" id="apx-loc-btn" title="<?= e(__('messages.location')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.5-7-11a7 7 0 0 1 14 0c0 4.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
        </button>
        <select class="apx-input" id="apx-burn" style="height:34px;width:auto;">
          <option value="none"><?= e(__('messages.burn_off')) ?></option>
          <option value="after_view"><?= e(__('messages.burn_view')) ?></option>
          <option value="after_time"><?= e(__('messages.burn_time', [':min' => 60])) ?></option>
        </select>
        <input class="apx-input" id="apx-burn-min" type="number" min="1" max="1440" value="60" style="height:34px;width:72px;display:none;" title="<?= e(__('messages.burn_minutes')) ?>">
      </div>
      <input type="file" id="apx-image-input" accept="image/*" hidden>
      <input type="file" id="apx-file-input" hidden>
      <div class="apx-emoji-pop" id="apx-emoji-pop" hidden>
        <?php foreach (['👍','❤️','😂','😮','😢','😡','🎉','🔥','👏','🙏','😍','🤔'] as $em): ?>
          <button type="button" data-emoji="<?= $em ?>"><?= $em ?></button>
        <?php endforeach; ?>
      </div>
      <textarea class="apx-textarea" id="apx-msg-input" rows="1" placeholder="<?= e(__('messages.placeholder')) ?>" style="min-height:42px;flex:1;"></textarea>
      <button class="apx-btn apx-btn--primary" id="apx-msg-send"><?= e(__('messages.send')) ?></button>
    </div>
  </div>
</div>

<script type="module" src="<?= asset('js/modules/chat.js') ?>"></script>
