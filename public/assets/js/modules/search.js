// APX · 全局搜索：实时建议、标签切换、动态流无限滚动、搜索历史、热门搜索
import { get, post, route } from '../core/http.js';
import { toast } from '../core/toast.js';
import * as modal from '../core/modal.js';
import { $, escapeHtml } from '../core/dom.js';
import { initFeed } from './feed-lib.js';

const base = window.APX.base;
const q = window.APX_QUERY || '';
const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);

function initSearchForm() {
  const form = document.getElementById('apx-search-form');
  if (!form) return;
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const input = form.querySelector('input[name="q"]');
    const v = input.value.trim();
    if (!v) return;
    location.href = route('/search?q=' + encodeURIComponent(v));
  });
}

/* ---------------- 实时建议 ---------------- */
function initSuggest() {
  const input = document.getElementById('apx-search-input');
  const box = document.getElementById('apx-suggest');
  if (!input || !box) return;

  let timer = null;
  let last = '';

  const hide = () => { box.hidden = true; box.innerHTML = ''; };

  input.addEventListener('input', () => {
    const v = input.value.trim();
    if (timer) clearTimeout(timer);
    if (v === '' || v === last) { hide(); return; }
    timer = setTimeout(() => fetchSuggest(v), 240);
  });
  input.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
  document.addEventListener('click', (e) => { if (!box.contains(e.target) && e.target !== input) hide(); });

  async function fetchSuggest(v) {
    last = v;
    try {
      const res = await get(base + '/api/search/suggest?q=' + encodeURIComponent(v));
      const d = res.data || {};
      const rows = [];
      (d.users || []).forEach((u) => rows.push({
        icon: '@', label: u.nickname || u.username, sub: '@' + u.username,
        url: route('/profile/' + u.username), group: t('search.suggest_users'),
      }));
      (d.topics || []).forEach((tp) => rows.push({
        icon: '#', label: '#' + tp.name + '#', sub: tp.post_count + ' ' + t('topic.posts'),
        url: route('/topic/' + tp.slug), group: t('search.suggest_topics'),
      }));
      (d.groups || []).forEach((g) => rows.push({
        icon: '◇', label: g.name, sub: g.member_count + ' ' + t('group.members'),
        url: route('/group/' + g.slug), group: t('search.suggest_groups'),
      }));

      if (!rows.length) {
        box.innerHTML = `<div class="apx-suggest__empty">${t('search.no_suggest')}</div>`;
        box.hidden = false;
        return;
      }
      box.innerHTML = rows.slice(0, 12).map((r) => `
        <a class="apx-suggest__item" href="${escapeHtml(r.url)}">
          <span class="apx-suggest__icon">${escapeHtml(r.icon)}</span>
          <span class="apx-suggest__label">${escapeHtml(r.label)}</span>
          <span class="apx-suggest__sub">${escapeHtml(r.sub)}</span>
          <span class="apx-suggest__group">${escapeHtml(r.group)}</span>
        </a>`).join('')
        + `<a class="apx-suggest__item apx-suggest__item--all" href="${escapeHtml(route('/search?q=' + encodeURIComponent(v)))}">
             <span class="apx-suggest__icon">🔍</span>
             <span class="apx-suggest__label">${escapeHtml(v)}</span>
             <span class="apx-suggest__group">${t('search.submit')}</span>
           </a>`;
      box.hidden = false;
    } catch (err) {
      hide();
    }
  }
}

function initTabs() {
  const tabs = document.querySelectorAll('.apx-tab');
  const setActive = (name) => {
    tabs.forEach((b) => b.classList.toggle('is-active', b.getAttribute('data-tab') === name));
    document.querySelectorAll('.apx-search-panel').forEach((p) => {
      p.hidden = p.getAttribute('data-panel') !== name;
    });
  };
  tabs.forEach((b) => b.addEventListener('click', () => setActive(b.getAttribute('data-tab'))));
}

function initPostScroll() {
  if (!q) return;
  const feed = document.getElementById('apx-feed');
  if (!feed) return;
  initFeed({
    container: 'apx-feed',
    varName: 'APX_POSTS',
    endpoint: base + '/api/search',
    extra: 'q=' + encodeURIComponent(q) + '&tab=posts',
    emptyText: window.apx ? window.apx.i18n.t('search.empty') : '',
  });
}

function initClearHistory() {
  const btn = document.getElementById('apx-clear-history');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    const ok = await modal.confirm({
      body: t('search.clear_history') + '?',
      danger: true,
      confirmText: t('search.clear_history'),
      cancelText: t('common.cancel'),
    });
    if (!ok) return;
    try {
      await post(base + '/api/search/history/clear', {});
      const box = document.getElementById('apx-history');
      if (box) box.remove();
    } catch (err) {
      toast.error(err.message);
    }
  });
}

initSearchForm();
initSuggest();
initTabs();
initPostScroll();
initClearHistory();
