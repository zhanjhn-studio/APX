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
        <?= icon('search', 20) ?>
      </button>
      <button class="apx-icon-btn" id="apx-thread-info" title="<?= e(__('messages.thread_info')) ?>"><?= icon('info', 20) ?></button>
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
      <button class="apx-icon-btn" id="apx-reply-cancel" title="<?= e(__('messages.cancel_reply')) ?>"><?= icon('close', 18) ?></button>
    </div>

    <div class="apx-thread__compose" id="apx-thread-compose" hidden>
      <div class="apx-thread__tools">
        <button class="apx-icon-btn" id="apx-emoji-btn" title="<?= e(__('messages.emoji')) ?>">😊</button>
        <button class="apx-icon-btn" id="apx-image-btn" title="<?= e(__('messages.attach_image')) ?>">
          <?= icon('image', 20) ?>
        </button>
        <button class="apx-icon-btn" id="apx-file-btn" title="<?= e(__('messages.attach_file')) ?>">
          <?= icon('paperclip', 20) ?>
        </button>
        <button class="apx-icon-btn" id="apx-loc-btn" title="<?= e(__('messages.location')) ?>">
          <?= icon('location', 20) ?>
        </button>
        <label class="apx-mic" title="<?= e(__('messages.mic')) ?>">
          <input type="checkbox" id="apx-mic">
          <svg class="microphone-slash" viewBox="0 0 24 24"><path d="M12 2a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/><path d="M4 4l16 16" stroke="currentColor" stroke-width="2"/></svg>
          <svg class="microphone" viewBox="0 0 24 24"><path d="M12 2a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
        </label>
        <label class="apx-mute" title="<?= e(__('messages.mute')) ?>">
          <input type="checkbox" id="apx-mute">
          <svg class="mute" viewBox="0 0 24 24"><path d="M4 9v6h4l5 5V4L8 9H4Z"/><path d="M17 9l4 6M21 9l-4 6" stroke="currentColor" stroke-width="2"/></svg>
          <svg class="voice" viewBox="0 0 24 24"><path d="M4 9v6h4l5 5V4L8 9H4Z"/><path d="M16 8a5 5 0 0 1 0 8M19 5a9 9 0 0 1 0 14"/></svg>
        </label>
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
<script>
  (function () {
    var mic = document.getElementById('apx-mic');
    var mute = document.getElementById('apx-mute');
    var rec = null, stream = null;
    if (mic) mic.addEventListener('change', async function () {
      if (mic.checked) {
        try {
          stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          var MR = window.MediaRecorder; if (MR) { rec = new MR(stream); rec.start(); }
          document.body.classList.add('apx-recording');
        } catch (e) { /* 无权限则仅切换视觉态 */ }
      } else {
        try { if (rec && rec.state !== 'inactive') rec.stop(); } catch (e) {}
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        document.body.classList.remove('apx-recording');
      }
    });
    if (mute) mute.addEventListener('change', function () {
      document.body.classList.toggle('apx-muted', mute.checked);
    });
  })();
</script>
