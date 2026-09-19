// APX · Realtime (SSE with polling fallback). 事件源来自 RealtimeInterface 实现，
// 前端只负责把增量渲染到徽标与提示，并通过 CustomEvent('apx:realtime') 暴露给页面模块。
import { get } from './core/http.js';
import { on, emit } from './core/store.js';
import { toast } from './core/toast.js';

let es = null;
let pollTimer = null;
let started = false;
let lastCounts = { notifications: 0, messages: 0 };

function handleEvent(name, payload) {
  emit('realtime:' + name, payload);
  emit('realtime', { name, payload });
  try {
    window.dispatchEvent(new CustomEvent('apx:realtime', { detail: { name, payload } }));
  } catch (e) { /* 老浏览器忽略 */ }

  if (name === 'notification') {
    const n = payload && typeof payload.unread === 'number' ? payload.unread : lastCounts.notifications + 1;
    setBadge('apx-noti-count', n);
    lastCounts.notifications = n;
    if (!location.pathname.includes('notifications')) toast.info(tr(payload && payload.body));
  } else if (name === 'message') {
    const n = lastCounts.messages + 1;
    setBadge('apx-msg-count', n);
    setBadge('apx-msg-count-m', n);
    lastCounts.messages = n;
    if (!location.pathname.includes('messages')) toast.info(tr('notification.message'));
  }
}

/** 通知 body 是语言包 key（可能带 | 附加文本），这里翻译后再展示。 */
function tr(body) {
  if (typeof body !== 'string' || body === '') return '';
  const key = body.split('|')[0];
  const dict = window.APX_I18N || {};
  return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : key;
}

function connectSSE() {
  if (typeof EventSource === 'undefined') return startPolling();
  try {
    es = new EventSource(route('/api/events'));
    es.onmessage = (e) => {
      try {
        const data = JSON.parse(e.data);
        handleEvent(data.event || 'message', data.payload || {});
      } catch (err) { /* ignore */ }
    };
    es.onerror = () => { if (es) { es.close(); es = null; } startPolling(); };
  } catch (e) { startPolling(); }
}

function startPolling() {
  if (pollTimer) return;
  pollTimer = setInterval(async () => {
    try {
      const json = await get('/api/events/poll');
      if (!json || !json.data) return;
      if (Array.isArray(json.data.events)) {
        json.data.events.forEach((ev) => handleEvent(ev.event, ev.payload));
      }
      if (json.data.counts) updateBadges(json.data.counts);
    } catch (e) { /* ignore network blips */ }
  }, 5000);
}

function setBadge(id, n) {
  const el = document.getElementById(id);
  if (!el) return;
  if (n > 0) { el.textContent = n > 99 ? '99+' : String(n); el.style.display = ''; }
  else { el.style.display = 'none'; }
}

function updateBadges(counts) {
  lastCounts.notifications = counts.notifications || 0;
  lastCounts.messages = counts.messages || 0;
  setBadge('apx-noti-count', lastCounts.notifications);
  setBadge('apx-msg-count', lastCounts.messages);
  setBadge('apx-msg-count-m', lastCounts.messages);
}

export function start() {
  if (started) return;
  started = true;
  connectSSE();
}

export function stop() {
  if (es) { es.close(); es = null; }
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  started = false;
}

export const realtime = { start, stop };
