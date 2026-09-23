// post.js —— 首页动态流：渲染、发布（含媒体/可见性）、无限滚动
import { get, post } from '../core/http.js';
import { t } from '../core/i18n.js';
import { toast } from '../core/toast.js';
import { renderPost, bindGlobal } from './postcard.js';

const base = window.APX.base;

function init() {
  bindGlobal();
  const feed = document.getElementById('apx-feed');
  if (!feed) return;

  const initial = window.APX_FEED || [];
  let oldest = 0;
  let loading = false;
  let hasMore = true;

  const appendPosts = (posts) => {
    if (!posts.length) {
      if (!feed.children.length) {
        feed.innerHTML = `<div class="apx-card apx-empty"><div class="apx-empty__title">${t('post.empty')}</div></div>`;
      }
      hasMore = false;
      return;
    }
    posts.forEach((p) => {
      const el = renderPost(p);
      feed.appendChild(el);
      const id = parseInt(p.id, 10);
      if (!oldest || id < oldest) oldest = id;
    });
    if (posts.length < 20) hasMore = false;
  };
  appendPosts(initial);

  let loaderEl = null;
  function showLoader() {
    if (!loaderEl) {
      loaderEl = document.createElement('div');
      loaderEl.className = 'apx-feed__loader';
      loaderEl.innerHTML = '<div class="apx-honeycomb"><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>';
    }
    if (!loaderEl.parentNode) feed.appendChild(loaderEl);
  }
  function hideLoader() { if (loaderEl && loaderEl.parentNode) loaderEl.remove(); }

  function showSkeleton(n) {
    const frag = document.createDocumentFragment();
    for (let i = 0; i < n; i++) {
      const sk = document.createElement('div');
      sk.className = 'apx-card apx-skel apx-skeleton';
      sk.innerHTML = '<div class="apx-skel__avatar"></div><div class="apx-skel__body"><div class="apx-skel__line w-3-5"></div><div class="apx-skel__line w-9"></div><div class="apx-skel__line w-1-2"></div></div>';
      frag.appendChild(sk);
    }
    feed.appendChild(frag);
  }
  function clearSkeleton() { feed.querySelectorAll('.apx-skel').forEach((x) => x.remove()); }

  const loadMore = async () => {
    if (loading || !hasMore) return;
    loading = true;
    showSkeleton(3); showLoader();
    try {
      const j = await get('/api/feed?before=' + oldest);
      clearSkeleton(); hideLoader();
      if (j && j.code === 0) {
        appendPosts(j.data.posts || []);
        hasMore = !!j.data.has_more;
      } else {
        hasMore = false;
      }
    } catch (e) { clearSkeleton(); hideLoader(); hasMore = false; }
    loading = false;
  };

  let ticking = false;
  window.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      ticking = false;
      if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 700) loadMore();
    });
  });
  // 首屏不足一屏时尝试补一页
  if (document.body.offsetHeight < window.innerHeight + 200) loadMore();

  initComposer();
}

/* ---------------- 发布器 ---------------- */
function initComposer() {
  const form = document.getElementById('apx-composer');
  if (!form) return;
  const body = form.querySelector('#apx-composer-body');
  const btn = form.querySelector('#apx-composer-btn');
  const mediaBox = form.querySelector('#apx-composer-media');
  const vis = form.querySelector('#apx-composer-vis');
  let media = [];

  form.querySelectorAll('input[type="file"][data-upload]').forEach((input) => {
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
          toast.error(t('upload.failed'));
        }
      } catch (e) { toast.error(t('upload.failed')); }
      btn.disabled = false;
    });
  });

  function renderPreview() {
    mediaBox.innerHTML = media.map((m, i) => {
      const src = base + '/assets/uploads/' + encodeURI(m.thumb || m.path);
      const thumb = m.type === 'video'
        ? `<video src="${src}" class="apx-media-thumb"></video>`
        : `<img src="${src}" class="apx-media-thumb" alt="">`;
      return `<div class="apx-media-preview"><button type="button" class="apx-media-preview__x" data-i="${i}">×</button>${thumb}</div>`;
    }).join('');
    mediaBox.querySelectorAll('.apx-media-preview__x').forEach((x) => {
      x.addEventListener('click', () => { media.splice(parseInt(x.dataset.i, 10), 1); renderPreview(); });
    });
  }

  const send = async () => {
    const text = body.value.trim();
    if (!text && !media.length) { body.focus(); return; }
    btn.disabled = true;
    const j = await post('/api/post', { body: text, visibility: vis ? vis.value : 'public', media });
    btn.disabled = false;
    if (j && j.code === 0) {
      body.value = '';
      media = [];
      renderPreview();
      const feed = document.getElementById('apx-feed');
      if (feed) {
        const empty = feed.querySelector('.apx-empty');
        if (empty) empty.remove();
        const el = renderPost({ id: j.data.id, user_id: window.APX.user.id, username: window.APX.user.username, nickname: window.APX.user.nickname, avatar: window.APX.user.avatar, body: text, visibility: vis ? vis.value : 'public', like_count: 0, comment_count: 0, share_count: 0, favorite_count: 0, created_at: new Date().toISOString().slice(0, 19).replace('T', ' '), liked: false, favorited: false, media, topics: [] });
        feed.insertBefore(el, feed.firstChild);
      }
      if (j.data.redirect) { /* stay on feed */ }
    } else {
      toast.error((j && j.message) ? j.message : t('post.create_failed'));
    }
  };
  btn.addEventListener('click', send);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
