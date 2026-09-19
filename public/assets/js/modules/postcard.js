// postcard.js —— 动态卡片统一渲染与交互（被 post / post-detail / discover / search / favorites / topic 复用）
import { get, post } from '../core/http.js';
import { t } from '../core/i18n.js';

const base = window.APX.base;
let bound = false;

/* ---------------- 工具 ---------------- */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function attr(s) { return esc(s); }

function up(path) {
  if (!path) return '';
  return base + '/assets/uploads/' + String(path).replace(/^\/+/, '');
}

function slugify(name) {
  const s = String(name).toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '');
  return s === '' ? 't' + Math.abs(hashCode(name)).toString(36) : s;
}
function hashCode(str) { let h = 0; for (let i = 0; i < str.length; i++) { h = (h << 5) - h + str.charCodeAt(i); h |= 0; } return h; }

function avatarHtml(avatar, name) {
  const initial = esc((name || '?').slice(0, 1));
  if (avatar) {
    return `<img class="apx-avatar apx-avatar--sm" src="${attr(up(avatar))}" alt="" data-initial="${initial}">`;
  }
  return `<span class="apx-avatar apx-avatar--sm">${initial}</span>`;
}

function timeAgo(iso) {
  if (!iso) return '';
  const t = new Date(String(iso).replace(' ', 'T') + 'Z');
  if (isNaN(t)) return String(iso).slice(0, 10);
  const s = Math.floor((Date.now() - t.getTime()) / 1000);
  if (s < 60) return t('common.just_now');
  if (s < 3600) return Math.floor(s / 60) + t('common.minutes_ago');
  if (s < 86400) return Math.floor(s / 3600) + t('common.hours_ago');
  if (s < 86400 * 7) return Math.floor(s / 86400) + t('common.days_ago');
  return String(iso).slice(0, 10);
}

/* ---------------- 正文格式化（链接 / #话题# / @提及） ---------------- */
function formatBody(text) {
  if (!text) return '';
  const re = /(https?:\/\/[^\s<]+)|(#([^#\s]{1,40})#)|(@[A-Za-z0-9_]{3,32})/g;
  let out = '', last = 0, m;
  while ((m = re.exec(text))) {
    out += esc(text.slice(last, m.index));
    if (m[1]) {
      out += `<a class="apx-link" href="${attr(m[1])}" target="_blank" rel="noopener noreferrer">${esc(m[1])}</a>`;
    } else if (m[2]) {
      const name = m[3];
      out += `<a class="apx-topic-link" href="${route('/topic/')}${encodeURIComponent(slugify(name))}">#${esc(name)}#</a>`;
    } else if (m[4]) {
      out += `<a class="apx-mention" href="${route('/profile/')}${attr(m[4].slice(1))}">${esc(m[4])}</a>`;
    }
    last = re.lastIndex;
  }
  out += esc(text.slice(last));
  return out;
}

/* ---------------- 可见性图标 ---------------- */
function visBadge(vis) {
  const map = {
    public: ['🌐', t('post.visibility.public')],
    friends: ['👥', t('post.visibility.friends')],
    close_friends: ['⭐', t('post.visibility.close')],
    private: ['🔒', t('post.visibility.private')],
  };
  const [icon, label] = map[vis] || map.public;
  return `<span class="apx-post__vis" title="${attr(label)}">${icon}</span>`;
}

/* ---------------- 媒体 ---------------- */
function mediaHtml(media) {
  if (!media || !media.length) return '';
  const cls = 'apx-post__media apx-post__media--' + Math.min(media.length, 4);
  const items = media.map((m) => {
    if (m.type === 'video') {
      return `<video class="apx-media apx-media--video" src="${attr(up(m.path))}" controls preload="metadata"></video>`;
    }
    const full = up(m.path);
    const thumb = up(m.thumb || m.path);
    return `<button type="button" class="apx-media apx-media--img" data-lightbox="${attr(full)}"><img src="${attr(thumb)}" alt="" data-initial=""></button>`;
  }).join('');
  return `<div class="${cls}">${items}</div>`;
}

/* ---------------- 话题 ---------------- */
function topicsHtml(topics) {
  if (!topics || !topics.length) return '';
  return `<div class="apx-post__topics">` + topics.map((tp) =>
    `<a class="apx-chip" href="${route('/topic/')}${encodeURIComponent(tp.slug)}">#${esc(tp.name)}#</a>`
  ).join('') + `</div>`;
}

/* ---------------- 转发源 ---------------- */
function originHtml(p) {
  if (!p.origin_id) return '';
  const name = p.origin_nickname || p.origin_username || '';
  return `<div class="apx-post__origin">
    <div class="apx-post__origin-head">${avatarHtml(p.origin_avatar, name)} <a class="apx-post__origin-name" href="${route('/profile/')}${attr(p.origin_username)}">${esc(name)}</a></div>
    <div class="apx-post__origin-body">${formatBody(p.origin_body || '')}</div>
  </div>`;
}

/* ---------------- 操作栏 ---------------- */
function actionsHtml(p) {
  return `<div class="apx-post__actions">
    <button class="apx-post__action${p.liked ? ' is-active' : ''}" data-act="like" data-post="${p.id}">
      <svg viewBox="0 0 24 24" fill="${p.liked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.5.8-1.3 2-2.5 4-2.5 3.5 0 5 3.5 3.5 6.5C19 16.5 12 21 12 21Z"/></svg>
      <span class="cnt">${p.like_count | 0}</span>
    </button>
    <button class="apx-post__action" data-act="comment" data-post="${p.id}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l1-4.5A8 8 0 1 1 21 12Z"/></svg>
      <span class="cnt">${p.comment_count | 0}</span>
    </button>
    <button class="apx-post__action" data-act="share" data-post="${p.id}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M12 3v13M8 7l4-4 4 4"/></svg>
      <span class="cnt">${p.share_count | 0}</span>
    </button>
    <button class="apx-post__action${p.favorited ? ' is-active' : ''}" data-act="favorite" data-post="${p.id}">
      <svg viewBox="0 0 24 24" fill="${p.favorited ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6-5.4-2.8L6.6 19.6l1-6L3.3 9.4l6-.9Z"/></svg>
      <span class="cnt">${p.favorite_count | 0}</span>
    </button>
  </div>`;
}

function headActionsHtml(p) {
  const me = parseInt(window.APX.user.id, 10);
  if ((p.user_id | 0) !== me) return '';
  return `<div class="apx-post__head-actions">
    <button class="apx-icon-btn" data-act="edit-post" data-post="${p.id}" title="${attr(t('post.edit'))}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4l10-10-4-4L4 16Z"/></svg></button>
    <button class="apx-icon-btn" data-act="delete-post" data-post="${p.id}" title="${attr(t('post.delete'))}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></button>
  </div>`;
}

/* ---------------- 动态卡片 ---------------- */
export function renderPost(p, opts = {}) {
  const me = parseInt(window.APX.user.id, 10);
  const name = p.nickname || p.username || '';
  const wrapper = document.createElement('article');
  wrapper.className = 'apx-card apx-post apx-anim-msg' + (opts.detail ? ' apx-post--detail' : '');
  wrapper.dataset.postId = p.id;
  wrapper.dataset.liked = p.liked ? '1' : '0';
  wrapper.dataset.favorited = p.favorited ? '1' : '0';
  wrapper.innerHTML = `
    <div class="apx-post__head">
      <a href="${route('/profile/')}${attr(p.username)}">${avatarHtml(p.avatar, name)}</a>
      <div class="apx-post__meta">
        <a class="apx-post__name" href="${route('/profile/')}${attr(p.username)}">${esc(name)}</a>
        <div class="apx-post__sub"><span class="apx-post__time">${timeAgo(p.created_at)}</span> · ${visBadge(p.visibility)}</div>
      </div>
      ${headActionsHtml(p)}
    </div>
    ${originHtml(p)}
    <a class="apx-post__body" href="${base}/post/${p.id}">${formatBody(p.body || '')}</a>
    ${mediaHtml(p.media)}
    ${topicsHtml(p.topics)}
    ${actionsHtml(p)}
    <div class="apx-post__comments" hidden>
      <div class="apx-comment-list"></div>
      <form class="apx-comment-form apx-flex apx-gap-2" data-post="${p.id}">
        <input class="apx-input apx-flex-1" name="body" placeholder="${attr(t('post.comment_placeholder'))}" maxlength="500">
        <button class="apx-btn apx-btn--primary apx-btn--sm" type="submit">${esc(t('post.send'))}</button>
      </form>
    </div>`;
  if (opts.detail && Array.isArray(p.comments)) {
    const list = wrapper.querySelector('.apx-comment-list');
    list.appendChild(renderCommentTree(p.comments, p.id));
  }
  fixImages(wrapper);
  return wrapper;
}

/* ---------------- 评论 ---------------- */
export function renderCommentTree(tree, postId) {
  const frag = document.createDocumentFragment();
  tree.forEach((c) => frag.appendChild(renderComment(c, postId, 0)));
  return frag;
}

export function renderComment(c, postId, depth) {
  const me = parseInt(window.APX.user.id, 10);
  const name = c.nickname || c.username || '';
  const el = document.createElement('div');
  el.className = 'apx-comment' + (depth > 0 ? ' apx-comment--child' : '');
  el.dataset.commentId = c.id;
  el.dataset.liked = c.liked ? '1' : '0';
  const replyTo = c.reply_to_user_id ? ` · @${esc(c.reply_to_user_id)}` : '';
  const ownerBtns = (c.user_id | 0) === me
    ? `<button class="apx-link-btn" data-act="comment-delete" data-comment="${c.id}">${esc(t('post.delete'))}</button>` : '';
  el.innerHTML = `
    <div class="apx-comment__head">
      <a href="${route('/profile/')}${attr(c.username)}">${avatarHtml(c.avatar, name)}</a>
      <div class="apx-flex-1">
        <a class="apx-comment__name" href="${route('/profile/')}${attr(c.username)}">${esc(name)}</a>
        <span class="apx-comment__time">${timeAgo(c.created_at)}</span>
      </div>
    </div>
    <div class="apx-comment__body">${formatBody(c.body || '')}${replyTo}</div>
    <div class="apx-comment__actions">
      <button class="apx-link-btn${c.liked ? ' is-active' : ''}" data-act="comment-like" data-comment="${c.id}">${esc(t('post.like'))} <span class="cnt">${c.like_count | 0}</span></button>
      <button class="apx-link-btn" data-act="comment-reply" data-comment="${c.id}" data-user="${attr(c.username)}">${esc(t('post.reply'))}</button>
      ${ownerBtns}
    </div>
    <div class="apx-comment__children"></div>`;
  const childBox = el.querySelector('.apx-comment__children');
  if (Array.isArray(c.children) && c.children.length) {
    c.children.forEach((ch) => childBox.appendChild(renderComment(ch, postId, depth + 1)));
  }
  return el;
}

function fixImages(root) {
  root.querySelectorAll('img').forEach((img) => {
    img.addEventListener('error', () => {
      const span = document.createElement('span');
      span.className = img.className;
      span.textContent = img.getAttribute('data-initial') || '?';
      if (img.parentNode) img.parentNode.replaceChild(span, img);
    });
  });
}

/* ---------------- 灯箱 ---------------- */
let lightboxEl = null;
function openLightbox(src) {
  if (!lightboxEl) {
    lightboxEl = document.createElement('div');
    lightboxEl.className = 'apx-lightbox';
    lightboxEl.innerHTML = `<div class="apx-lightbox__backdrop"></div><img class="apx-lightbox__img" src="" alt=""><button class="apx-lightbox__close">×</button>`;
    lightboxEl.addEventListener('click', (e) => {
      if (e.target === lightboxEl || e.target.classList.contains('apx-lightbox__backdrop') || e.target.classList.contains('apx-lightbox__close')) {
        lightboxEl.classList.remove('is-open');
      }
    });
    document.body.appendChild(lightboxEl);
  }
  lightboxEl.querySelector('img').src = src;
  lightboxEl.classList.add('is-open');
}

/* ---------------- 全局交互绑定（只绑一次） ---------------- */
export function bindGlobal() {
  if (bound) return;
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('submit', onSubmit);
}

function findPost(id) { return document.querySelector(`.apx-post[data-post-id="${id}"]`); }

async function onClick(e) {
  const tgt = e.target.closest('[data-act]');
  if (!tgt) {
    const lb = e.target.closest('[data-lightbox]');
    if (lb) { e.preventDefault(); openLightbox(lb.getAttribute('data-lightbox')); }
    return;
  }
  const act = tgt.dataset.act;

  if (act === 'like') {
    const id = tgt.dataset.post; const card = findPost(id);
    const j = await post('/api/post/like', { post_id: id });
    if (j && j.code === 0) {
      card.dataset.liked = j.data.liked ? '1' : '0';
      const svg = tgt.querySelector('svg');
      if (svg) svg.setAttribute('fill', j.data.liked ? 'currentColor' : 'none');
      tgt.classList.toggle('is-active', !!j.data.liked);
      tgt.querySelector('.cnt').textContent = j.data.count;
    }
  } else if (act === 'favorite') {
    const id = tgt.dataset.post; const card = findPost(id);
    const j = await post('/api/post/favorite', { post_id: id });
    if (j && j.code === 0) {
      card.dataset.favorited = j.data.favorited ? '1' : '0';
      const svg = tgt.querySelector('svg');
      if (svg) svg.setAttribute('fill', j.data.favorited ? 'currentColor' : 'none');
      tgt.classList.toggle('is-active', !!j.data.favorited);
      tgt.querySelector('.cnt').textContent = j.data.count;
      document.dispatchEvent(new CustomEvent('apx:favorite', { detail: { postId: id, favorited: !!j.data.favorited } }));
    }
  } else if (act === 'share') {
    const id = tgt.dataset.post;
    if (!confirm(t('post.confirm_repost'))) return;
    const j = await post('/api/post/share', { post_id: id });
    if (j && j.code === 0) {
      tgt.querySelector('.cnt').textContent = j.data.count;
      tgt.classList.add('is-active');
      if (j.data.redirect) location.href = j.data.redirect;
    }
  } else if (act === 'comment') {
    const id = tgt.dataset.post; const card = findPost(id);
    const box = card.querySelector('.apx-post__comments');
    box.hidden = !box.hidden;
    if (!box.hidden && !box.dataset.loaded) {
      box.dataset.loaded = '1';
      loadComments(id, box);
    }
  } else if (act === 'edit-post') {
    editPost(tgt.dataset.post);
  } else if (act === 'delete-post') {
    const id = tgt.dataset.post;
    if (!confirm(t('post.confirm_delete'))) return;
    const j = await post('/api/post/delete', { post_id: id });
    if (j && j.code === 0) { const c = findPost(id); if (c) c.remove(); }
  } else if (act === 'comment-like') {
    const cid = tgt.dataset.comment;
    const j = await post('/api/post/comment/like', { comment_id: cid });
    if (j && j.code === 0) {
      tgt.classList.toggle('is-active', !!j.data.liked);
      tgt.querySelector('.cnt').textContent = j.data.count;
    }
  } else if (act === 'comment-reply') {
    const cid = tgt.dataset.comment; const uname = tgt.dataset.user;
    const card = tgt.closest('.apx-post');
    const form = card.querySelector('.apx-comment-form');
    form.dataset.parent = cid;
    form.dataset.replyTo = tgt.closest('.apx-comment').dataset.commentId;
    form.querySelector('input').value = '@' + uname + ' ';
    form.querySelector('input').focus();
    card.querySelector('.apx-post__comments').hidden = false;
  } else if (act === 'comment-delete') {
    const cid = tgt.dataset.comment;
    if (!confirm(t('post.confirm_delete'))) return;
    const j = await post('/api/post/comment/delete', { comment_id: cid });
    if (j && j.code === 0) { const c = document.querySelector(`.apx-comment[data-comment-id="${cid}"]`); if (c) c.remove(); }
  } else if (act === 'follow') {
    const btn = tgt; const uid = btn.dataset.follow;
    const j = await post('/api/follow', { user_id: uid });
    if (j && j.code === 0) {
      const on = !!j.data.following;
      btn.textContent = on ? t('post.following') : t('post.follow');
      btn.classList.toggle('apx-btn--soft', on);
    }
  } else if (act === 'topic-follow') {
    const btn = tgt; const slug = btn.dataset.slug;
    const following = btn.dataset.following === '1';
    const j = following ? await post('/api/topic/unfollow', { slug }) : await post('/api/topic/follow', { slug });
    if (j && j.code === 0) {
      btn.dataset.following = following ? '0' : '1';
      btn.textContent = following ? t('topic.follow') : t('topic.following');
      btn.classList.toggle('is-active', !following);
    }
  }
}

async function loadComments(postId, box) {
  const list = box.querySelector('.apx-comment-list');
  const j = await get('/api/post/comments?post_id=' + postId);
  if (j && j.code === 0) {
    list.appendChild(renderCommentTree(j.data.comments || [], postId));
  }
}

async function onSubmit(e) {
  const form = e.target.closest('.apx-comment-form');
  if (!form) return;
  e.preventDefault();
  const input = form.querySelector('input');
  const body = input.value.trim();
  if (!body) return;
  const postId = form.dataset.post;
  const parent = form.dataset.parent || null;
  const replyTo = form.dataset.replyTo || null;
  const j = await post('/api/post/comment', { post_id: postId, body, parent_id: parent, reply_to: replyTo });
  if (j && j.code === 0) {
    const card = findPost(postId);
    const list = card.querySelector('.apx-comment-list');
    if (parent) {
      const parentEl = document.querySelector(`.apx-comment[data-comment-id="${parent}"] .apx-comment__children`);
      if (parentEl) parentEl.appendChild(renderComment(j.data.comment, postId, 1));
    } else {
      list.appendChild(renderComment(j.data.comment, postId, 0));
    }
    input.value = '';
    form.dataset.parent = ''; form.dataset.replyTo = '';
    const cntEl = card.querySelector('[data-act="comment"] .cnt');
    if (cntEl) cntEl.textContent = (parseInt(cntEl.textContent, 10) || 0) + 1;
  }
}

function editPost(id) {
  const card = findPost(id);
  const bodyEl = card.querySelector('.apx-post__body');
  if (card.querySelector('.apx-post__edit')) return;
  const original = bodyEl.textContent;
  const edit = document.createElement('div');
  edit.className = 'apx-post__edit';
  edit.innerHTML = `<textarea class="apx-textarea" rows="3">${esc(original)}</textarea>
    <div class="apx-flex apx-justify-end apx-gap-2 apx-mt-2">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-edit-cancel>${esc(t('common.cancel'))}</button>
      <button class="apx-btn apx-btn--primary apx-btn--sm" data-edit-save>${esc(t('post.save'))}</button>
    </div>`;
  bodyEl.after(edit);
  bodyEl.hidden = true;
  edit.querySelector('[data-edit-cancel]').addEventListener('click', () => { edit.remove(); bodyEl.hidden = false; });
  edit.querySelector('[data-edit-save]').addEventListener('click', async () => {
    const val = edit.querySelector('textarea').value.trim();
    if (!val) return;
    const j = await post('/api/post/update', { post_id: id, body: val, visibility: 'public' });
    if (j && j.code === 0) {
      bodyEl.innerHTML = formatBody(val);
      edit.remove(); bodyEl.hidden = false;
    }
  });
}
