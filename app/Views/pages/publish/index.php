<?php
use App\Core\I18n;

$user = $user ?? [];
$name = $user['nickname'] ?? $user['username'] ?? '';
$initial = !empty($name) ? mb_substr($name, 0, 1) : '?';
$handle = '@' . ($user['username'] ?? '');
$avatar = !empty($user['avatar']) ? asset('uploads/' . ltrim($user['avatar'], '/')) : '';
?>
<div class="apx-page-head">
  <a class="apx-link" href="<?= route('/home') ?>">← <?= e(__('common.action.back')) ?></a>
</div>

<div class="apx-publish">
  <div class="apx-container">
    <div class="apx-line-hero apx-publish__hero">
      <h1 class="apx-publish__hero-title"><?= e(__('publish.title')) ?></h1>
      <p class="apx-publish__hero-sub"><?= e(__('publish.hero_sub')) ?></p>
    </div>
    <div class="apx-publish__alerts" id="apx-publish-alerts"></div>

    <div class="apx-publish__grid">
      <!-- 撰写区 -->
      <section class="apx-compose">
        <div class="apx-compose__head">
          <span class="apx-avatar"><?= e($initial) ?></span>
          <div>
            <div class="apx-post__name"><?= e($name) ?></div>
            <div class="apx-compose__hint"><?= e(__('publish.share_thought')) ?></div>
          </div>
        </div>

        <textarea class="apx-compose__textarea" id="apx-publish-body" maxlength="2000"
          placeholder="<?= e(__('post.placeholder')) ?>"></textarea>

        <!-- 上传拖拽区（Uiverse Yaya12085） -->
        <div class="apx-dropzone" id="apx-publish-dropzone" title="<?= e(__('post.drop_hint')) ?>">
          <span class="apx-dropzone__icon"><svg viewBox="0 0 24 24"><path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></span>
          <span class="apx-dropzone__text"><?= e(__('post.drop_hint')) ?></span>
          <input type="file" accept="image/*,video/*" multiple hidden data-upload="mixed">
        </div>

        <div class="apx-compose__media" id="apx-publish-media"></div>

        <div class="apx-compose__tools">
          <label class="apx-icon-btn" title="<?= e(__('post.add_image')) ?>">
            <?= icon('image', 20) ?>
            <input type="file" accept="image/*" multiple hidden data-upload="image">
          </label>
          <label class="apx-icon-btn" title="<?= e(__('post.add_video')) ?>">
            <?= icon('video', 20) ?>
            <input type="file" accept="video/*" hidden data-upload="video">
          </label>
          <label class="apx-expand" title="<?= e(__('post.fullscreen_media')) ?>">
            <input type="checkbox" id="opt-expand">
            <svg class="expand" viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            <svg class="compress" viewBox="0 0 24 24"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>
          </label>
          <span class="apx-tag" id="apx-publish-count">0 / 2000</span>
        </div>

        <div class="apx-compose__foot">
          <button class="apx-btn-expand apx-btn-expand--ghost" id="apx-publish-clear" type="button" title="<?= e(__('publish.clear')) ?>">
            <span class="sign"><svg viewBox="0 0 24 24"><path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></span>
            <span class="text"><?= e(__('publish.clear')) ?></span>
          </button>
          <label class="apx-star" title="<?= e(__('publish.save_favorite')) ?>">
            <input type="checkbox" id="opt-favorite">
            <svg viewBox="0 0 24 24"><path d="M12 2l3 6.5 7 .9-5 4.8 1.2 7L12 17.8 5.8 21.2 7 14.2 2 9.4l7-.9z"/></svg>
            <span class="apx-publish__opt-label"><?= e(__('publish.add_favorite')) ?></span>
          </label>
          <button class="apx-btn apx-btn--primary" id="apx-publish-btn" type="button">
            <span class="apx-btn-label"><?= e(__('post.send')) ?></span>
            <span class="apx-spinner" id="apx-publish-spin" style="display:none"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg></span>
          </button>
        </div>
      </section>

      <!-- 侧栏：身份 + 可见性 + 选项 -->
      <aside class="apx-publish__side">
        <div class="apx-profile-card">
          <div class="apx-profile-card__cover"></div>
          <?php if ($avatar): ?><img class="apx-profile-card__avatar" src="<?= e($avatar) ?>" alt=""><?php else: ?><span class="apx-profile-card__avatar"><?= e($initial) ?></span><?php endif; ?>
          <div class="apx-profile-card__name"><?= e($name) ?></div>
          <div class="apx-profile-card__sub"><?= e($handle) ?></div>
          <button class="apx-profile-card__btn" type="button" disabled><?= e(__('publish.posting_as')) ?></button>
        </div>

        <div class="apx-card">
          <div class="apx-publish__sec-title"><?= e(__('post.visibility')) ?></div>
          <div class="apx-vis" style="--total:4" id="apx-vis">
            <input type="radio" name="vis" id="vis-public" value="public" checked>
            <label for="vis-public"><?= e(__('post.visibility.public')) ?></label>
            <input type="radio" name="vis" id="vis-friends" value="friends">
            <label for="vis-friends"><?= e(__('post.visibility.friends')) ?></label>
            <input type="radio" name="vis" id="vis-close" value="close_friends">
            <label for="vis-close"><?= e(__('post.visibility.close')) ?></label>
            <input type="radio" name="vis" id="vis-private" value="private">
            <label for="vis-private"><?= e(__('post.visibility.private')) ?></label>
            <div class="glider-container"><div class="glider"></div></div>
          </div>
        </div>

        <div class="apx-card">
          <div class="apx-publish__sec-title"><?= e(__('publish.options')) ?></div>

          <div class="apx-publish__opt">
            <span class="apx-publish__opt-label"><?= e(__('publish.allow_share')) ?></span>
            <label class="apx-ios-check">
              <input type="checkbox" id="opt-share" checked>
              <span class="cw">
                <span class="cb"></span>
                <svg class="ci" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline class="cp" points="4 12 10 18 20 6"/></svg>
              </span>
            </label>
          </div>

          <div class="apx-publish__opt">
            <span class="apx-publish__opt-label"><?= e(__('publish.pin')) ?></span>
            <label class="apx-toggle">
              <input type="checkbox" id="opt-pin">
              <span class="apx-toggle__track"><span class="apx-toggle__thumb"></span></span>
            </label>
          </div>

          <div class="apx-publish__opt">
            <span class="apx-publish__opt-label"><?= e(__('publish.important')) ?></span>
            <label class="apx-check-anim">
              <input type="checkbox" id="opt-important">
              <span class="ck"><svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6"/></svg></span>
            </label>
          </div>

          <div class="apx-publish__opt">
            <span class="apx-publish__opt-label"><?= e(__('publish.encrypt')) ?></span>
            <label class="apx-lock" title="<?= e(__('publish.encrypt')) ?>">
              <input type="checkbox" id="opt-lock">
              <svg viewBox="0 0 28 28"><path class="bling" d="M9 9l.8 1.6L12 11l-2.2 1.4L9 14l-.8-1.6L6 11l2.2-1.4Z"/><path class="lock" d="M10 13v-2a4 4 0 0 1 8 0v2"/><path class="lockb" d="M8 13h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z"/></svg>
            </label>
          </div>
        </div>

        <div class="apx-card apx-grid-bg">
          <div class="apx-publish__sec-title"><?= e(__('publish.topic')) ?></div>
          <div class="apx-topic-list" id="apx-topics">
            <?php foreach (['摄影', '旅行', '美食', '科技', '音乐', '运动'] as $t): ?>
            <label class="apx-radio-check">
              <input type="radio" name="topic" value="<?= e($t) ?>">
              <svg viewBox="0 0 24 24"><path d="M5 12a7 7 0 1 0 14 0 7 7 0 1 0-14 0"/><polyline points="8 12 11 15 16 9"/></svg>
              <span><?= e($t) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div>

<script>
(function () {
  const base = window.APX.base;
  const $ = (s, r = document) => r.querySelector(s);
  const alerts = $('#apx-publish-alerts');
  const body = $('#apx-publish-body');
  const mediaBox = $('#apx-publish-media');
  const countEl = $('#apx-publish-count');
  const btn = $('#apx-publish-btn');
  const spin = $('#apx-publish-spin');
  let media = [];

  function showAlert(type, msg) {
    const el = document.createElement('div');
    el.className = 'apx-alert apx-alert--' + type;
    el.innerHTML = '<div class="apx-alert__body">' + msg + '</div>';
    alerts.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3600);
  }

  // 预选类型（来自 FAB 的 ?type=）
  const type = new URLSearchParams(location.search).get('type');
  if (type === 'image' || type === 'video') {
    const inp = document.querySelector('input[data-upload="' + type + '"]');
    if (inp) setTimeout(() => inp.click(), 400);
  }

  // 字数统计
  body.addEventListener('input', () => {
    countEl.textContent = body.value.length + ' / 2000';
  });

  // 媒体上传
  document.querySelectorAll('input[type="file"][data-upload]').forEach((input) => {
    input.addEventListener('change', async () => {
      const files = Array.from(input.files || []);
      input.value = '';
      if (!files.length) return;
      btn.disabled = true;
      try {
        const fd = new FormData();
        files.forEach((f) => fd.append('files[]', f));
        const j = await fetch(route('/api/upload'), { method: 'POST', body: fd, headers: { 'X-CSRF-Token': window.APX.csrf || '' } });
        const r = await j.json();
        if (r.code === 0 && r.data.items) {
          r.data.items.forEach((it) => { media.push(it); renderPreview(); });
        } else {
          showAlert('danger', (r && r.message) ? r.message : '<?= e(__('upload.failed')) ?>');
        }
      } catch (e) {
        showAlert('danger', '<?= e(__('upload.failed')) ?>');
      }
      btn.disabled = false;
    });
  });

  function renderPreview() {
    mediaBox.innerHTML = media.map((m, i) => {
      let p = m.url || m.thumb || m.path || '';
      if (p && p.indexOf('assets/') === -1 && p.indexOf('uploads/') !== -1) p = 'assets/' + p;
      const src = (base ? base.replace(/\/$/, '') : '') + '/' + String(p).replace(/^\/+/, '');
      const thumb = m.type === 'video'
        ? '<video src="' + src + '" muted></video>'
        : '<img src="' + src + '" alt="">';
      return '<div class="apx-media-preview"><button type="button" class="apx-media-preview__x" data-i="' + i + '">&times;</button>' + thumb + '</div>';
    }).join('');
    mediaBox.querySelectorAll('.apx-media-preview__x').forEach((x) => {
      x.addEventListener('click', () => { media.splice(parseInt(x.dataset.i, 10), 1); renderPreview(); });
    });
  }

  // 发布
  btn.addEventListener('click', async () => {
    const text = body.value.trim();
    if (!text && !media.length) { body.focus(); showAlert('warning', '<?= e(__('post.empty')) ?>'); return; }
    const vis = (document.querySelector('input[name="vis"]:checked') || {}).value || 'public';
    const payload = {
      body: text,
      visibility: vis,
      media: media,
      allow_share: $('#opt-share').checked ? 1 : 0,
      pin: $('#opt-pin').checked ? 1 : 0,
      important: $('#opt-important').checked ? 1 : 0,
      favorite: $('#opt-favorite').checked ? 1 : 0,
      locked: $('#opt-lock').checked ? 1 : 0,
    };
    const topic = (document.querySelector('input[name="topic"]:checked') || {}).value || '';
    payload.topic = topic;
    btn.disabled = true;
    spin.style.display = 'inline-block';
    window.APX.showLoading();
    try {
      const j = await fetch(route('/api/post'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APX.csrf || '' },
        body: JSON.stringify(payload),
      });
      const r = await j.json();
      if (r && r.code === 0) {
        showAlert('success', '<?= e(__('publish.success')) ?>');
        body.value = ''; countEl.textContent = '0 / 2000';
        media = []; renderPreview();
        document.querySelectorAll('input[name="topic"]').forEach((x) => x.checked = false);
        if (r.data && r.data.redirect) { /* 停留当前页 */ }
      } else {
        showAlert('danger', (r && r.message) ? r.message : '<?= e(__('post.create_failed')) ?>');
      }
    } catch (e) {
      showAlert('danger', '<?= e(__('post.create_failed')) ?>');
    } finally {
      btn.disabled = false;
      spin.style.display = 'none';
      window.APX.hideLoading();
    }
  });

  // 拖拽区（Uiverse Yaya12085 · 点击/拖拽上传）
  const dz = document.getElementById('apx-publish-dropzone');
  if (dz) {
    const dzInput = dz.querySelector('input[type="file"]');
    dz.addEventListener('click', () => { if (dzInput) dzInput.click(); });
    ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('is-drag'); }));
    ['dragleave', 'dragend', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('is-drag'); }));
    dz.addEventListener('drop', (e) => {
      const files = Array.from((e.dataTransfer && e.dataTransfer.files) || []);
      if (!files.length || !dzInput) return;
      const dt = new DataTransfer();
      files.forEach((f) => dt.items.add(f));
      dzInput.files = dt.files;
      dzInput.dispatchEvent(new Event('change'));
    });
  }

  // 媒体全屏（Uiverse catraco expand/compress）
  const expand = document.getElementById('opt-expand');
  if (expand) {
    expand.addEventListener('change', () => {
      if (expand.checked) { if (mediaBox.requestFullscreen) mediaBox.requestFullscreen(); }
      else { if (document.exitFullscreen) document.exitFullscreen(); }
    });
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement && expand.checked) expand.checked = false; });
  }

  // 清空草稿（Uiverse vinodjangid07 扩展按钮）
  const clearBtn = document.getElementById('apx-publish-clear');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      body.value = ''; media = []; renderPreview();
      countEl.textContent = '0 / 2000';
      document.querySelectorAll('input[name="topic"]').forEach((x) => x.checked = false);
      body.focus();
    });
  }
})();
</script>
