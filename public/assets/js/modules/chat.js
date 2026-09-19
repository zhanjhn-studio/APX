// APX · 私聊 / 群聊
// 会话列表、消息流、引用回复、会话内搜索、附件（图片/文件）、表情/位置、撤回、表情回应、
// 已读回执与人数、置顶 / 免打扰 / 删除会话、正在输入、轮询增量。
import { get, post, route } from '../core/http.js';
import { toast } from '../core/toast.js';
import * as modal from '../core/modal.js';
import { $, $$, create, escapeHtml } from '../core/dom.js';

const base = window.APX_CONFIG ? (window.APX_CONFIG.baseUrl || '') : '';
const t = (k, p) => (window.apx ? window.apx.i18n.t(k, p) : k);

const chat = document.getElementById('apx-chat');
if (chat) initChat();

let currentCid = 0;
let currentType = 'direct';
let oldestId = 0;
let newestId = 0;
let pollTimer = null;
let emojiMode = 'send';
let reactTarget = 0;
let replyTarget = 0;
let lastTypingSent = 0;
let searchTimer = null;

const uid = () => parseInt(chat.dataset.uid, 10) || 0;
const uploadUrl = () => base + '/public/assets/uploads/';

function initChat() {
  const openId = parseInt(chat.dataset.open, 10) || 0;

  $('#apx-conv-items').addEventListener('click', (e) => {
    const menuBtn = e.target.closest('[data-conv-menu]');
    if (menuBtn) {
      e.preventDefault();
      e.stopPropagation();
      convMenu(parseInt(menuBtn.dataset.convMenu, 10), menuBtn);
      return;
    }
    const item = e.target.closest('[data-conv]');
    if (item) { e.preventDefault(); openConv(parseInt(item.dataset.conv, 10)); }
  });

  $('#apx-new-chat').addEventListener('click', () => { location.href = route('/friends'); });

  // emoji
  $('#apx-emoji-btn').addEventListener('click', () => {
    emojiMode = 'send';
    const p = $('#apx-emoji-pop');
    p.hidden = !p.hidden;
  });
  $('#apx-emoji-pop').addEventListener('click', (e) => {
    const b = e.target.closest('[data-emoji]');
    if (!b) return;
    if (emojiMode === 'react' && reactTarget) reactMessage(reactTarget, b.dataset.emoji);
    else sendMessage(b.dataset.emoji, 'emoji');
    $('#apx-emoji-pop').hidden = true;
  });

  // 附件
  $('#apx-image-btn').addEventListener('click', () => $('#apx-image-input').click());
  $('#apx-file-btn').addEventListener('click', () => $('#apx-file-input').click());
  $('#apx-image-input').addEventListener('change', (e) => uploadAndSend(e.target, 'image'));
  $('#apx-file-input').addEventListener('change', (e) => uploadAndSend(e.target, 'file'));

  $('#apx-loc-btn').addEventListener('click', sendLocation);

  $('#apx-burn').addEventListener('change', (e) => {
    $('#apx-burn-min').style.display = e.target.value === 'after_time' ? '' : 'none';
  });

  // 发送与快捷键：Enter 发送，Shift+Enter 换行
  $('#apx-msg-send').addEventListener('click', () => sendMessage());
  $('#apx-msg-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });
  $('#apx-msg-input').addEventListener('input', typingHeartbeat);

  $('#apx-reply-cancel').addEventListener('click', clearReply);

  // 会话内搜索
  $('#apx-thread-search').addEventListener('click', () => toggleSearch(true));
  $('#apx-thread-search-close').addEventListener('click', () => toggleSearch(false));
  $('#apx-thread-search-input').addEventListener('input', () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 320);
  });

  $('#apx-thread-info').addEventListener('click', showThreadInfo);
  $('#apx-thread-body').addEventListener('click', onThreadClick);
  $('#apx-conv-search').addEventListener('input', (e) => filterConv(e.target.value));

  if (openId) openConv(openId);
}

/* ---------------- 会话列表操作 ---------------- */

function convMenu(cid, anchor) {
  const item = document.querySelector(`#apx-conv-items [data-conv="${cid}"]`);
  if (!item) return;
  const pinned = item.dataset.pinned === '1';
  const muted = item.dataset.muted === '1';
  document.querySelectorAll('.apx-conv-menu').forEach((m) => m.remove());
  const menu = create(`<div class="apx-menu apx-conv-menu is-open">
      <a class="apx-menu__item" data-act="pin">${pinned ? t('messages.unpin') : t('messages.pin')}</a>
      <a class="apx-menu__item" data-act="mute">${muted ? t('messages.unmute') : t('messages.mute')}</a>
      <div class="apx-menu__sep"></div>
      <a class="apx-menu__item is-danger" data-act="delete">${t('messages.delete')}</a>
    </div>`);
  document.body.appendChild(menu);
  const rect = anchor.getBoundingClientRect();
  menu.style.position = 'fixed';
  menu.style.left = Math.max(8, rect.left - 130) + 'px';
  menu.style.top = (rect.bottom + 6) + 'px';

  const close = () => menu.remove();
  setTimeout(() => document.addEventListener('click', close, { once: true }), 0);

  menu.addEventListener('click', async (e) => {
    const act = e.target.closest('[data-act]');
    if (!act) return;
    e.preventDefault();
    close();
    const kind = act.dataset.act;
    try {
      if (kind === 'delete') {
        const ok = await modal.confirm({
          body: t('messages.confirm_delete'),
          danger: true,
          confirmText: t('messages.delete'),
          cancelText: t('common.cancel'),
        });
        if (!ok) return;
        const res = await post(base + '/api/message/delete', { conversation_id: cid });
        toast.success(res.message);
        item.remove();
        if (currentCid === cid) resetThread();
        if (res.data && res.data.redirect) history.replaceState(null, '', res.data.redirect);
        return;
      }
      const on = kind === 'pin' ? (pinned ? 0 : 1) : (muted ? 0 : 1);
      const res = await post(base + '/api/message/' + kind, { conversation_id: cid, on });
      toast.success(res.message);
      item.dataset[kind === 'pin' ? 'pinned' : 'muted'] = String(on);
      item.classList.toggle('is-pinned', kind === 'pin' ? on === 1 : pinned);
      // 重新排序/刷新标记：简单可靠的整页刷新列表状态
      setTimeout(() => location.reload(), 360);
    } catch (err) {
      toast.error(err.message);
    }
  });
}

function resetThread() {
  currentCid = 0;
  oldestId = 0;
  newestId = 0;
  stopPolling();
  $('#apx-thread-empty').hidden = false;
  $('#apx-thread-head').hidden = true;
  $('#apx-thread-body').hidden = true;
  $('#apx-thread-compose').hidden = true;
  $('#apx-thread-body').innerHTML = '';
  clearReply();
  toggleSearch(false);
  $('#apx-thread-typing').hidden = true;
}

/* ---------------- 打开会话 ---------------- */

function openConv(cid) {
  currentCid = cid;
  const item = document.querySelector(`#apx-conv-items [data-conv="${cid}"]`);
  currentType = item ? (item.dataset.type || 'direct') : 'direct';
  $$('#apx-conv-items [data-conv]').forEach((el) =>
    el.classList.toggle('is-active', parseInt(el.dataset.conv, 10) === cid));
  $('#apx-thread-empty').hidden = true;
  $('#apx-thread-head').hidden = false;
  $('#apx-thread-body').hidden = false;
  $('#apx-thread-compose').hidden = false;

  const nameEl = item ? item.querySelector('.apx-chat__item-name span') : null;
  $('#apx-thread-name').textContent = nameEl ? nameEl.textContent.replace(/[↑🔇]/g, '').trim() : '';
  const wrap = document.getElementById('apx-thread-avatar-wrap');
  const avEl = item ? item.querySelector('.apx-avatar') : null;
  wrap.innerHTML = avEl ? avEl.outerHTML.replace('apx-avatar"', 'apx-avatar apx-avatar--sm"') : '<span class="apx-avatar apx-avatar--sm"></span>';

  clearReply();
  toggleSearch(false);
  if (item) item.querySelector('.apx-chat__unread')?.remove();

  loadMessages(cid, 0, true);
  startPolling();
  history.replaceState(null, '', route('/messages?c=' + cid));
}

async function loadMessages(cid, before = 0, replace = false) {
  try {
    const json = await get(base + '/api/messages?c=' + cid + '&before=' + before);
    const list = (json && json.data && json.data.messages) || [];
    const body = $('#apx-thread-body');
    if (replace) { body.innerHTML = ''; oldestId = 0; }
    list.forEach((m) => {
      if (oldestId === 0 || m.id < oldestId) oldestId = m.id;
      if (m.id > newestId) newestId = m.id;
      body.appendChild(renderMessage(m));
    });
    renderTyping((json.data && json.data.typing) || []);
    if (replace && body.lastChild) body.scrollTop = body.scrollHeight;
  } catch (e) {
    toast.error(e.message);
  }
}

/** 跳转到某条消息（搜索结果点击）。 */
async function jumpTo(messageId) {
  if (!currentCid) return;
  try {
    const json = await get(base + '/api/messages?c=' + currentCid + '&around=' + messageId);
    const list = (json && json.data && json.data.messages) || [];
    const body = $('#apx-thread-body');
    body.innerHTML = '';
    oldestId = 0;
    list.forEach((m) => {
      if (oldestId === 0 || m.id < oldestId) oldestId = m.id;
      body.appendChild(renderMessage(m));
    });
    toggleSearch(false);
    const target = body.querySelector(`.apx-msg[data-msg="${messageId}"]`);
    if (target) {
      target.classList.add('is-highlight');
      target.scrollIntoView({ block: 'center', behavior: 'smooth' });
      setTimeout(() => target.classList.remove('is-highlight'), 2400);
    }
  } catch (e) {
    toast.error(e.message);
  }
}

/* ---------------- 渲染 ---------------- */

const TYPE_LABEL = { image: 'messages.type.image', file: 'messages.type.file', location: 'messages.type.location' };

function renderMessage(m) {
  const mine = parseInt(m.sender_id, 10) === uid();
  const el = create(`<div class="apx-msg ${mine ? 'apx-msg--me' : ''}" data-msg="${m.id}" data-type="${escapeHtml(m.type || 'text')}">
      ${mine ? '' : avatarHtml(m)}
      <div class="apx-msg__col">
        <div class="apx-msg__quote" hidden></div>
        <div class="apx-msg__bubble"></div>
        <div class="apx-msg__meta"></div>
        <div class="apx-msg__actions">
          <button data-act="reply" data-msg="${m.id}">${t('messages.reply')}</button>
          <button data-act="copy" data-msg="${m.id}">${t('messages.copy')}</button>
          ${mine ? `<button data-act="recall" data-msg="${m.id}">${t('messages.recall')}</button>` : ''}
          <button data-act="react" data-msg="${m.id}">${t('messages.react')}</button>
        </div>
        <div class="apx-reactions" data-reactions="${m.id}"></div>
      </div>
    </div>`);

  const bubble = el.querySelector('.apx-msg__bubble');
  const quote = el.querySelector('.apx-msg__quote');

  if (m.reply_to_id && m.reply_body !== undefined && m.reply_body !== null) {
    quote.hidden = false;
    const who = m.reply_sender === uid() ? t('messages.you') : (m.reply_nickname || m.reply_username || '');
    const text = m.reply_type && m.reply_type !== 'text' ? t(TYPE_LABEL[m.reply_type] || 'messages.type.file') : m.reply_body;
    quote.innerHTML = `<b>${escapeHtml(who)}</b> ${escapeHtml(String(text).slice(0, 80))}`;
  }

  if (m.recalled_at || m.type === 'recall') {
    el.classList.add('apx-msg--recall');
    bubble.textContent = t('messages.recalled');
  } else if (m.type === 'image') {
    bubble.appendChild(mediaNode(m, 'image'));
  } else if (m.type === 'file') {
    bubble.appendChild(mediaNode(m, 'file'));
  } else if (m.type === 'location') {
    let loc = {};
    try { loc = JSON.parse(m.body); } catch (e) { loc = {}; }
    const a = document.createElement('a');
    a.href = 'https://maps.google.com/?q=' + encodeURIComponent((loc.lat || '') + ',' + (loc.lng || ''));
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.textContent = '📍 ' + (loc.label ? loc.label + ' ' : '') + t('messages.location');
    bubble.appendChild(a);
  } else if (m.type === 'emoji') {
    bubble.textContent = m.body || '';
    bubble.classList.add('apx-msg__bubble--emoji');
  } else {
    bubble.textContent = m.body || '';
  }

  let meta = (m.created_at || '').slice(11, 16);
  if (m.burn_mode && m.burn_mode !== 'none') meta += ' · 🔥';
  el.querySelector('.apx-msg__meta').textContent = meta;
  return el;
}

function mediaNode(m, kind) {
  let meta = {};
  try { meta = JSON.parse(m.body); } catch (e) { meta = {}; }
  const path = String(meta.path || '').replace(/^\/+/, '');
  const url = uploadUrl() + path;
  if (kind === 'image') {
    const img = document.createElement('img');
    img.className = 'apx-msg__image';
    img.src = url;
    img.alt = meta.name || 'image';
    img.loading = 'lazy';
    img.addEventListener('click', () => window.open(url, '_blank', 'noopener'));
    return img;
  }
  const a = document.createElement('a');
  a.className = 'apx-msg__file';
  a.href = url;
  a.target = '_blank';
  a.rel = 'noopener noreferrer';
  const size = meta.size ? ' · ' + Math.max(1, Math.round(meta.size / 1024)) + ' KB' : '';
  a.innerHTML = `<span class="apx-msg__file-icon">📎</span><span>${escapeHtml(meta.name || t('messages.attach_file'))}${size}</span>`;
  return a;
}

function avatarHtml(m) {
  const initial = (m.nickname || m.username || '?').slice(0, 1);
  if (m.avatar) {
    return `<img class="apx-avatar apx-avatar--sm" src="${uploadUrl()}${String(m.avatar).replace(/^\/+/, '')}" alt="" onerror="this.outerHTML='<span class=&quot;apx-avatar apx-avatar--sm&quot;>${escapeHtml(initial)}</span>'">`;
  }
  return `<span class="apx-avatar apx-avatar--sm">${escapeHtml(initial)}</span>`;
}

function renderTyping(names) {
  const box = $('#apx-thread-typing');
  if (!names || !names.length) { box.hidden = true; box.textContent = ''; return; }
  box.hidden = false;
  box.textContent = names.length === 1
    ? t('messages.typing_user', { ':name': names[0] })
    : t('messages.typing_many', { ':count': names.length });
}

/* ---------------- 发送 ---------------- */

async function sendMessage(bodyText, type = 'text') {
  if (!currentCid) return;
  const input = $('#apx-msg-input');
  const body = type === 'text' ? (bodyText != null ? bodyText : input.value.trim()) : bodyText;
  if (!body) return;
  const burn = $('#apx-burn').value;
  const burnValue = burn === 'after_time' ? Math.max(1, parseInt($('#apx-burn-min').value, 10) || 60) : 0;
  try {
    const payload = {
      conversation_id: currentCid,
      type,
      body,
      burn_mode: burn,
      burn_value: burnValue,
    };
    if (replyTarget) payload.reply_to_id = replyTarget;
    const j = await post(base + '/api/message/send', payload);
    const msg = j && j.data ? j.data.message : null;
    if (msg) appendMessage(msg);
    if (type === 'text') input.value = '';
    clearReply();
  } catch (err) {
    toast.error(t('messages.send_failed'));
  }
}

function appendMessage(msg) {
  const b = $('#apx-thread-body');
  if (oldestId === 0 || msg.id < oldestId) oldestId = msg.id;
  if (msg.id > newestId) newestId = msg.id;
  b.appendChild(renderMessage(msg));
  b.scrollTop = b.scrollHeight;
}

async function uploadAndSend(inputEl, kind) {
  const file = inputEl.files && inputEl.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('files[]', file);
  try {
    const res = await post(base + '/api/upload', fd);
    const item = (res.data && res.data.items && res.data.items[0]) || null;
    if (!item) throw new Error(t('upload.failed'));
    await sendMessage(JSON.stringify({
      path: item.path,
      name: file.name,
      size: file.size,
      mime: file.type,
      thumb: item.thumb || '',
      w: item.w || 0,
      h: item.h || 0,
    }), kind);
  } catch (err) {
    toast.error(err.message || t('upload.failed'));
  } finally {
    inputEl.value = '';
  }
}

function sendLocation() {
  if (!navigator.geolocation) { toast.warning(t('messages.location')); return; }
  navigator.geolocation.getCurrentPosition(async (pos) => {
    await sendMessage(JSON.stringify({
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      label: '',
    }), 'location');
  }, () => toast.warning(t('messages.location')));
}

function typingHeartbeat() {
  if (!currentCid) return;
  const now = Date.now();
  if (now - lastTypingSent < 3000) return;
  lastTypingSent = now;
  post(base + '/api/message/typing', { conversation_id: currentCid }).catch(() => {});
}

/* ---------------- 消息操作 ---------------- */

function onThreadClick(e) {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const msgId = parseInt(btn.dataset.msg, 10);
  const el = btn.closest('.apx-msg');
  switch (btn.dataset.act) {
    case 'recall':
      recallMessage(msgId);
      break;
    case 'react':
      emojiMode = 'react';
      reactTarget = msgId;
      $('#apx-emoji-pop').hidden = false;
      break;
    case 'reply':
      setReply(msgId, el);
      break;
    case 'copy':
      copyMessage(el);
      break;
  }
}

function setReply(msgId, el) {
  if (!el) return;
  const bubble = el.querySelector('.apx-msg__bubble');
  replyTarget = msgId;
  $('#apx-reply-text').textContent = (bubble ? bubble.textContent : '').slice(0, 80);
  $('#apx-reply-bar').hidden = false;
  $('#apx-msg-input').focus();
}

function clearReply() {
  replyTarget = 0;
  $('#apx-reply-bar').hidden = true;
  $('#apx-reply-text').textContent = '';
}

async function copyMessage(el) {
  if (!el) return;
  const text = el.querySelector('.apx-msg__bubble').textContent || '';
  try {
    if (navigator.clipboard) await navigator.clipboard.writeText(text);
    else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    toast.success(t('messages.copied'));
  } catch (e) {
    toast.error(t('messages.copied'));
  }
}

async function recallMessage(msgId) {
  try {
    const j = await post(base + '/api/message/recall', { message_id: msgId });
    if (j && j.data && j.data.recalled) {
      const el = document.querySelector(`.apx-msg[data-msg="${msgId}"]`);
      if (el) {
        el.classList.add('apx-msg--recall');
        el.querySelector('.apx-msg__bubble').textContent = t('messages.recalled');
      }
    } else {
      toast.warning(t('auth.login.expired'));
    }
  } catch (err) {
    toast.error(err.message);
  }
}

async function reactMessage(msgId, emoji) {
  try {
    const j = await post(base + '/api/message/react', { message_id: msgId, emoji });
    const data = (j && j.data) || { added: false, count: 0, emoji };
    const box = document.querySelector(`.apx-reactions[data-reactions="${msgId}"]`);
    if (box) {
      box.innerHTML = '';
      if (data.count > 0) {
        const chip = document.createElement('span');
        chip.className = 'apx-reaction' + (data.added ? ' is-on' : '');
        chip.textContent = data.emoji + (data.count > 1 ? ' ' + data.count : '');
        box.appendChild(chip);
      }
    }
  } catch (err) {
    toast.error(err.message);
  }
}

/* ---------------- 会话内搜索 ---------------- */

function toggleSearch(show) {
  const bar = $('#apx-thread-searchbar');
  const results = $('#apx-thread-search-results');
  bar.hidden = !show;
  if (!show) {
    results.hidden = true;
    results.innerHTML = '';
    $('#apx-thread-search-input').value = '';
  } else {
    $('#apx-thread-search-input').focus();
  }
}

async function runSearch() {
  const q = $('#apx-thread-search-input').value.trim();
  const box = $('#apx-thread-search-results');
  if (!currentCid || q === '') { box.hidden = true; box.innerHTML = ''; return; }
  try {
    const res = await get(base + '/api/message/search?c=' + currentCid + '&q=' + encodeURIComponent(q));
    const items = (res.data && res.data.items) || [];
    box.hidden = false;
    if (!items.length) {
      box.innerHTML = `<div class="apx-chat-search__empty">${t('messages.search_empty')}</div>`;
      return;
    }
    box.innerHTML = `<div class="apx-chat-search__count">${t('messages.search_count', { ':count': items.length })}</div>` +
      items.map((m) => `<button class="apx-chat-search__item" data-jump="${m.id}">
          <span class="apx-chat-search__who">${escapeHtml(m.nickname || m.username || '')}</span>
          <span class="apx-chat-search__text">${escapeHtml(m.body || '')}</span>
          <span class="apx-chat-search__time">${escapeHtml(String(m.created_at).slice(5, 16))}</span>
        </button>`).join('');
    box.querySelectorAll('[data-jump]').forEach((b) => b.addEventListener('click', () => jumpTo(parseInt(b.dataset.jump, 10))));
  } catch (err) {
    toast.error(err.message);
  }
}

/* ---------------- 会话信息 ---------------- */

async function showThreadInfo() {
  if (!currentCid) return;
  const item = document.querySelector(`#apx-conv-items [data-conv="${currentCid}"]`);
  const name = $('#apx-thread-name').textContent;
  const muted = item && item.dataset.muted === '1';
  const pinned = item && item.dataset.pinned === '1';
  const isGroup = currentType === 'group';
  const node = modal.open(`
    <div class="apx-modal__title">${escapeHtml(name)}</div>
    <div class="apx-modal__body">
      <div class="apx-update-row"><span>${t('messages.group')} / ${t('messages.chat')}</span><b>${isGroup ? t('messages.group') : t('messages.chat')}</b></div>
      <div class="apx-update-row"><span>${t('messages.pin')}</span><b>${pinned ? 'ON' : 'OFF'}</b></div>
      <div class="apx-update-row"><span>${t('messages.mute')}</span><b>${muted ? 'ON' : 'OFF'}</b></div>
      <div class="apx-update-row"><span>${t('messages.read_by')}</span><b id="apx-info-read">—</b></div>
    </div>
    <div class="apx-modal__foot">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-modal-close>${t('common.done')}</button>
    </div>`);
  const mineList = document.querySelectorAll('.apx-msg--me');
  const last = mineList.length ? mineList[mineList.length - 1] : null;
  const readEl = node.querySelector('#apx-info-read');
  if (last && readEl) {
    try {
      const res = await get(base + '/api/message/receipts?message_id=' + last.dataset.msg);
      readEl.textContent = String((res.data && res.data.count) || 0);
    } catch (e) { readEl.textContent = '—'; }
  }
}

/* ---------------- 轮询 ---------------- */

function startPolling() {
  stopPolling();
  pollTimer = setInterval(syncOnce, 4000);
}

/** 拉取增量消息与「正在输入」状态。 */
async function syncOnce() {
  if (!currentCid) return;
  try {
    const json = await get(base + '/api/messages?c=' + currentCid + '&before=' + (newestId || 0));
    const list = (json && json.data && json.data.messages) || [];
    const body = $('#apx-thread-body');
    const nearBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 90;
    list.forEach((m) => {
      if (document.querySelector(`.apx-msg[data-msg="${m.id}"]`)) return;
      if (oldestId === 0 || m.id < oldestId) oldestId = m.id;
      if (m.id > newestId) newestId = m.id;
      body.appendChild(renderMessage(m));
    });
    renderTyping((json.data && json.data.typing) || []);
    if (list.length && nearBottom) body.scrollTop = body.scrollHeight;
  } catch (e) { /* 轮询失败静默，下轮重试 */ }
}

function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

function filterConv(q) {
  q = (q || '').toLowerCase();
  $$('#apx-conv-items [data-conv]').forEach((el) => {
    const nameEl = el.querySelector('.apx-chat__item-name span');
    const name = nameEl ? nameEl.textContent.toLowerCase() : '';
    el.style.display = name.indexOf(q) >= 0 ? '' : 'none';
  });
}

document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopPolling();
  else if (currentCid) startPolling();
});

// 实时通道收到本会话新消息时立即同步，无需等待下一次轮询
window.addEventListener('apx:realtime', (e) => {
  const detail = e.detail || {};
  if (detail.name !== 'message' || !currentCid) return;
  const cid = detail.payload ? Number(detail.payload.conversation_id) : 0;
  if (cid === Number(currentCid)) syncOnce();
});
