// APX · 群组详情：群动态 / 群聊 / 成员 / 公告 / 管理
import { $, $$, delegate, ready, escapeHtml } from '../core/dom.js';
import * as http from '../core/http.js';
import * as modal from '../core/modal.js';
import { toast } from '../core/toast.js';

const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);
const G = window.APX_GROUP || { id: 0, permissions: {}, is_member: false, role: null };
const gid = () => G.id;

/* ---------------- 标签切换 ---------------- */
function initTabs() {
  const nav = document.getElementById('apx-group-nav');
  if (!nav) return;
  nav.addEventListener('click', (e) => {
    const tab = e.target.closest('.apx-tab');
    if (!tab) return;
    const key = tab.getAttribute('data-tab');
    nav.querySelectorAll('.apx-tab').forEach((x) => x.classList.toggle('is-active', x === tab));
    $$('.apx-tabpanel').forEach((p) => p.classList.toggle('is-active', p.getAttribute('data-panel') === key));
    if (key === 'chat') loadChat(false);
    stopChatPoll();
    if (key === 'chat') startChatPoll();
  });
}

/* ---------------- 群动态 ---------------- */
function postCard(p) {
  const name = p.nickname || p.username || '';
  const canDelete = Number(p.user_id) === Number(window.APX && window.APX.user ? window.APX.user.id : 0) || G.permissions.manage;
  return `<article class="apx-card apx-gpost" data-post="${p.id}" data-author="${p.user_id}">
    <div class="apx-post__head">
      <span class="apx-avatar apx-avatar--md">${escapeHtml(String(name).slice(0, 1))}</span>
      <div class="apx-post__meta">
        <span class="apx-post__name">${escapeHtml(name)}</span>
        <span class="apx-post__time">@${escapeHtml(p.username || '')} · ${escapeHtml(String(p.created_at || '').slice(0, 16))}</span>
      </div>
      ${canDelete ? `<button class="apx-icon-btn apx-gpost__del" data-gpost-delete="${p.id}" title="${t('post.delete')}">✕</button>` : ''}
    </div>
    <div class="apx-post__body">${escapeHtml(p.body || '').replace(/\n/g, '<br>')}</div>
    <div class="apx-post__actions">
      <button class="apx-post__action${p.liked ? ' is-active' : ''}" data-gpost-like="${p.id}" data-liked="${p.liked ? 1 : 0}">
        <svg viewBox="0 0 24 24" fill="${p.liked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.5.8-1.3 2-2.5 4-2.5 3.5 0 5 3.5 3.5 6.5C19 16.5 12 21 12 21Z"/></svg>
        <span class="cnt">${p.like_count || 0}</span>
      </button>
    </div>
  </article>`;
}

let postCursor = 0;

function initialPostCursor() {
  const el = document.getElementById('apx-group-posts');
  return el ? Number(el.getAttribute('data-cursor') || 0) : 0;
}

async function loadPosts(append) {
  const list = document.getElementById('apx-group-posts');
  if (!list) return;
  if (append && postCursor <= 0) return;
  try {
    const res = await http.get(`/api/group/posts?group_id=${gid()}&before=${append ? postCursor : 0}`);
    const items = (res.data && res.data.items) || [];
    if (!append) list.innerHTML = '';
    if (!append && !items.length) {
      list.innerHTML = `<div class="apx-empty"><div class="apx-empty__title">${t('group.posts_empty')}</div></div>`;
      const more = document.getElementById('apx-group-posts-more');
      if (more) more.remove();
      return;
    }
    const empty = list.querySelector('.apx-empty');
    if (empty) empty.remove();
    items.forEach((p) => { list.insertAdjacentHTML('beforeend', postCard(p)); });
    postCursor = items.length ? items[items.length - 1].id : postCursor;
    if (items.length < 20) {
      const more = document.getElementById('apx-group-posts-more');
      if (more) more.remove();
    }
  } catch (err) {
    toast.error(err.message);
  }
}

async function createPost(btn) {
  const input = document.getElementById('apx-group-post-input');
  if (!input) return;
  const body = input.value.trim();
  if (!body) { toast.error(t('validation.required')); return; }
  btn.disabled = true;
  btn.classList.add('is-loading');
  try {
    const res = await http.post('/api/group/post', { group_id: gid(), body });
    toast.success(res.message);
    input.value = '';
    const post = res.data && res.data.post;
    const list = document.getElementById('apx-group-posts');
    if (post && list) {
      const empty = list.querySelector('.apx-empty');
      if (empty) empty.remove();
      list.insertAdjacentHTML('afterbegin', postCard(post));
    } else {
      loadPosts(false);
    }
  } catch (err) {
    toast.error(err.message);
  } finally {
    btn.disabled = false;
    btn.classList.remove('is-loading');
  }
}

/* ---------------- 群聊 ---------------- */
let chatCursor = 0;
let chatTimer = null;

function chatBubble(m, meId) {
  const mine = Number(m.sender_id) === Number(meId);
  const recalled = !!m.recalled_at || m.type === 'recall';
  const body = recalled
    ? `<span class="apx-gchat__recalled">${t('messages.preview_recall')}</span>`
    : (m.type === 'system'
      ? `<span class="apx-gchat__recalled">${escapeHtml(t(m.body || ''))}</span>`
      : escapeHtml(m.body || '').replace(/\n/g, '<br>'));
  return `<div class="apx-gchat__msg${mine ? ' is-mine' : ''}" data-msg="${m.id}" data-uid="${m.sender_id}">
    ${!mine ? `<span class="apx-avatar apx-avatar--sm">${escapeHtml(String(m.nickname || m.username || '?').slice(0, 1))}</span>` : ''}
    <div class="apx-gchat__bubble">
      ${!mine ? `<div class="apx-gchat__who">${escapeHtml(m.nickname || m.username || '')}</div>` : ''}
      <div class="apx-gchat__text">${body}</div>
      <div class="apx-gchat__time">${escapeHtml(String(m.created_at || '').slice(0, 16))}</div>
    </div>
  </div>`;
}

async function loadChat(silent) {
  const box = document.getElementById('apx-gchat-body');
  if (!box) return;
  try {
    const res = await http.get(`/api/group/chat?group_id=${gid()}&before=0`);
    const items = (res.data && res.data.items) || [];
    const meId = window.APX && window.APX.user ? window.APX.user.id : 0;
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    if (!items.length) {
      box.innerHTML = `<div class="apx-gchat__loading apx-text-faint">${t('messages.no_more')}</div>`;
      return;
    }
    box.innerHTML = items.map((m) => chatBubble(m, meId)).join('');
    chatCursor = items[items.length - 1].id;
    if (!silent || nearBottom) box.scrollTop = box.scrollHeight;
  } catch (err) {
    if (!silent) toast.error(err.message);
  }
}

function startChatPoll() {
  stopChatPoll();
  chatTimer = setInterval(() => loadChat(true), 5000);
}
function stopChatPoll() {
  if (chatTimer) { clearInterval(chatTimer); chatTimer = null; }
}

async function sendChat(btn) {
  const input = document.getElementById('apx-gchat-input');
  if (!input) return;
  const body = input.value.trim();
  if (!body) return;
  btn.disabled = true;
  try {
    const res = await http.post('/api/group/chat/send', { group_id: gid(), body });
    input.value = '';
    const meId = window.APX && window.APX.user ? window.APX.user.id : 0;
    const box = document.getElementById('apx-gchat-body');
    const loading = box && box.querySelector('.apx-gchat__loading');
    if (loading) loading.remove();
    if (box && res.data && res.data.message) {
      box.insertAdjacentHTML('beforeend', chatBubble(res.data.message, meId));
      box.scrollTop = box.scrollHeight;
    }
  } catch (err) {
    toast.error(err.message);
  } finally {
    btn.disabled = false;
  }
}

/* ---------------- 成员管理 ---------------- */
async function memberAction(url, payload, confirmText) {
  if (confirmText) {
    const ok = await modal.confirm({ body: confirmText, danger: true, confirmText: t('common.action.confirm'), cancelText: t('common.cancel') });
    if (!ok) return;
  }
  try {
    const res = await http.post(url, payload);
    toast.success(res.message);
    setTimeout(() => location.reload(), 420);
  } catch (err) {
    toast.error(err.message);
  }
}

/* ---------------- 公告 ---------------- */
function annCard(a) {
  return `<div class="apx-card apx-gann${a.is_pinned ? ' is-pinned' : ''}" data-ann="${a.id}">
    <div class="apx-gann__head">
      <span class="apx-avatar apx-avatar--sm">${escapeHtml(String(a.nickname || a.username || '?').slice(0, 1))}</span>
      <span class="apx-gann__name">${escapeHtml(a.nickname || a.username || '')}</span>
      <span class="apx-gann__time">${escapeHtml(String(a.created_at || '').slice(0, 16))}</span>
      ${a.is_pinned ? `<span class="apx-badge apx-badge--primary">${t('group.pinned')}</span>` : ''}
    </div>
    <div class="apx-gann__body">${escapeHtml(a.body || '').replace(/\n/g, '<br>')}</div>
  </div>`;
}

async function createAnnouncement(btn) {
  const input = document.getElementById('apx-gann-input');
  const pin = document.getElementById('apx-gann-pin');
  if (!input) return;
  const body = input.value.trim();
  if (!body) { toast.error(t('validation.required')); return; }
  btn.disabled = true;
  try {
    const res = await http.post('/api/group/announcement', {
      group_id: gid(),
      body,
      pinned: pin && pin.checked ? 1 : 0,
    });
    toast.success(res.message);
    input.value = '';
    if (pin) pin.checked = false;
    setTimeout(() => location.reload(), 420);
  } catch (err) {
    toast.error(err.message);
  } finally {
    btn.disabled = false;
  }
}

/* ---------------- 管理 ---------------- */
async function saveGroup(btn) {
  btn.disabled = true;
  btn.classList.add('is-loading');
  try {
    const res = await http.post('/api/group/update', {
      group_id: gid(),
      name: document.getElementById('apx-gset-name').value.trim(),
      description: document.getElementById('apx-gset-desc').value.trim(),
      visibility: document.getElementById('apx-gset-vis').value,
    });
    toast.success(res.message);
  } catch (err) {
    toast.error(err.message);
  } finally {
    btn.disabled = false;
    btn.classList.remove('is-loading');
  }
}

async function uploadAvatar(file) {
  const fd = new FormData();
  fd.append('files[]', file);
  try {
    const res = await http.post('/api/upload', fd);
    const item = res.data && res.data.items && res.data.items[0];
    if (!item) throw new Error(t('upload.failed'));
    await http.post('/api/group/update', { group_id: gid(), avatar: item.path });
    toast.success(t('group.updated'));
  } catch (err) {
    toast.error(err.message);
  }
}

function inviteDialog() {
  const node = modal.open(`
    <div class="apx-modal__title">${t('group.invite')}</div>
    <div class="apx-field">
      <span class="apx-field__label">${t('settings.username_placeholder')}</span>
      <input class="apx-input" name="username" maxlength="32">
    </div>
    <div class="apx-modal__foot">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-modal-close>${t('common.cancel')}</button>
      <button class="apx-btn apx-btn--primary apx-btn--sm" data-invite>${t('group.invite')}</button>
    </div>`);
  const btn = node.querySelector('[data-invite]');
  btn.addEventListener('click', async () => {
    const username = node.querySelector('[name="username"]').value.trim();
    if (!username) return;
    btn.disabled = true;
    try {
      const res = await http.post('/api/group/invite', { group_id: gid(), username });
      toast.success(res.message);
      modal.close();
    } catch (err) {
      toast.error(err.message);
    } finally {
      btn.disabled = false;
    }
  });
}

/* ---------------- 初始化 ---------------- */
ready(() => {
  initTabs();
  postCursor = initialPostCursor();

  const postSend = document.getElementById('apx-group-post-send');
  if (postSend) postSend.addEventListener('click', () => createPost(postSend));

  const more = document.getElementById('apx-group-posts-more');
  if (more) more.addEventListener('click', () => loadPosts(true));

  const chatSend = document.getElementById('apx-gchat-send');
  if (chatSend) chatSend.addEventListener('click', () => sendChat(chatSend));
  const chatInput = document.getElementById('apx-gchat-input');
  if (chatInput) chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(chatSend); }
  });

  const gannSend = document.getElementById('apx-gann-send');
  if (gannSend) gannSend.addEventListener('click', () => createAnnouncement(gannSend));

  const gsetSave = document.getElementById('apx-gset-save');
  if (gsetSave) gsetSave.addEventListener('click', () => saveGroup(gsetSave));
  const gsetAvatar = document.getElementById('apx-gset-avatar');
  if (gsetAvatar) gsetAvatar.addEventListener('change', () => {
    if (gsetAvatar.files && gsetAvatar.files[0]) uploadAvatar(gsetAvatar.files[0]);
  });
  const gsetTransfer = document.getElementById('apx-gset-transfer');
  if (gsetTransfer) gsetTransfer.addEventListener('click', async () => {
    const sel = document.getElementById('apx-gset-owner');
    const target = sel && sel.value;
    if (!target) { toast.error(t('group.manage.transfer_pick')); return; }
    const ok = await modal.confirm({ body: t('group.confirm_transfer'), danger: true, confirmText: t('common.action.confirm'), cancelText: t('common.cancel') });
    if (!ok) return;
    await memberAction('/api/group/transfer', { group_id: gid(), user_id: target });
  });
  const gsetDisband = document.getElementById('apx-gset-disband');
  if (gsetDisband) gsetDisband.addEventListener('click', () => memberAction(
    '/api/group/disband', { group_id: gid() }, t('group.confirm_disband')
  ));

  delegate(document, 'click', '[data-gpost-like]', async (e, btn) => {
    e.preventDefault();
    try {
      const res = await http.post('/api/group/post/like', { group_id: gid(), post_id: btn.getAttribute('data-gpost-like') });
      const cnt = btn.querySelector('.cnt');
      const svg = btn.querySelector('svg');
      if (res.data) {
        if (cnt) cnt.textContent = res.data.count;
        btn.classList.toggle('is-active', !!res.data.liked);
        btn.setAttribute('data-liked', res.data.liked ? '1' : '0');
        if (svg) svg.setAttribute('fill', res.data.liked ? 'currentColor' : 'none');
      }
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-gpost-delete]', async (e, btn) => {
    e.preventDefault();
    const ok = await modal.confirm({ body: t('post.confirm_delete'), danger: true, confirmText: t('post.delete'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/group/post/delete', { group_id: gid(), post_id: btn.getAttribute('data-gpost-delete') });
      toast.success(res.message);
      const card = btn.closest('.apx-gpost');
      if (card) card.remove();
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-gmember-kick]', (e, btn) => {
    e.preventDefault();
    memberAction('/api/group/member/kick', { group_id: gid(), user_id: btn.getAttribute('data-gmember-kick') }, t('group.confirm_kick'));
  });
  delegate(document, 'click', '[data-gmember-mute]', (e, btn) => {
    e.preventDefault();
    const minutes = prompt(t('group.mute_minutes'), '60');
    if (minutes === null) return;
    memberAction('/api/group/member/mute', { group_id: gid(), user_id: btn.getAttribute('data-gmember-mute'), minutes: parseInt(minutes, 10) || 0 });
  });
  delegate(document, 'click', '[data-gmember-role]', (e, btn) => {
    e.preventDefault();
    memberAction('/api/group/member/role', {
      group_id: gid(),
      user_id: btn.getAttribute('data-gmember-role'),
      role: btn.getAttribute('data-role'),
    });
  });

  delegate(document, 'click', '[data-greq]', async (e, btn) => {
    e.preventDefault();
    try {
      const res = await http.post('/api/group/request/respond', { request_id: btn.getAttribute('data-greq'), action: btn.getAttribute('data-action') });
      toast.success(res.message);
      const row = btn.closest('.apx-user-row');
      if (row) row.remove();
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-gann-delete]', async (e, btn) => {
    e.preventDefault();
    const ok = await modal.confirm({ body: t('common.action.delete') + '?', danger: true, confirmText: t('common.action.delete'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/group/announcement/delete', { group_id: gid(), id: btn.getAttribute('data-gann-delete') });
      toast.success(res.message);
      const card = btn.closest('.apx-gann');
      if (card) card.remove();
    } catch (err) { toast.error(err.message); }
  });
  delegate(document, 'click', '[data-gann-pin]', async (e, btn) => {
    e.preventDefault();
    const pinned = btn.getAttribute('data-pinned') === '1' ? 0 : 1;
    try {
      await http.post('/api/group/announcement/pin', { group_id: gid(), id: btn.getAttribute('data-gann-pin'), pinned });
      setTimeout(() => location.reload(), 300);
    } catch (err) { toast.error(err.message); }
  });

  const inviteOpen = document.querySelector('[data-group-invite-open]');
  if (inviteOpen) inviteOpen.addEventListener('click', inviteDialog);

  delegate(document, 'click', '[data-group-join]', async (e, btn) => {
    e.preventDefault();
    try {
      const res = await http.post('/api/group/join', { group_id: btn.getAttribute('data-group-join') });
      toast.success(res.message);
      setTimeout(() => location.reload(), 420);
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-group-leave]', async (e, btn) => {
    e.preventDefault();
    const ok = await modal.confirm({ body: t('group.confirm_leave'), danger: true, confirmText: t('group.leave'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/group/leave', { group_id: btn.getAttribute('data-group-leave') });
      toast.success(res.message);
      const redirect = (res.data && res.data.redirect) || http.route('/groups');
      setTimeout(() => { location.href = redirect; }, 400);
    } catch (err) { toast.error(err.message); }
  });
});
