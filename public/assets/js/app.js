// APX · Application entry. Native ES modules, no build step.
import * as dom from './core/dom.js';
import * as http from './core/http.js';
import * as toast from './core/toast.js';
import * as modal from './core/modal.js';
import * as i18n from './core/i18n.js';
import * as theme from './core/theme.js';
import * as store from './core/store.js';
import { realtime } from './realtime.js';

window.apx = { dom, http, toast, modal, i18n, theme, store, realtime, loader };

// 云朵 loader：覆盖在卡片/区块之上，不破坏原 DOM 与事件
const CLOUD_SVG = `
<svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
  <g id="cloud">
    <rect x="22" y="48" width="56" height="26" rx="13"></rect>
    <g></g>
    <g>
      <path d="M50 34a16 16 0 1 1 -11.3 4.7" stroke-width="5" stroke-linecap="round"></path>
      <path d="M38.7 30l1.2 9 9 -1.2" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"></path>
    </g>
  </g>
  <g id="shapes"><g><g>
    <circle cx="20" cy="60" r="15"></circle>
    <circle cx="50" cy="45" r="20"></circle>
    <circle cx="80" cy="60" r="15"></circle>
  </g></g></g>
  <g id="lines"><g>
    <line x1="34" y1="20" x2="66" y2="20" stroke-linecap="round"></line>
  </g></g>
</svg>`;

const loader = {
  markup() {
    return '<div class="apx-loader-cloud" role="status" aria-label="Loading">' + CLOUD_SVG + '</div>';
  },
  show(target) {
    if (!target || target.querySelector(':scope > .apx-card-loader')) return;
    const ov = document.createElement('div');
    ov.className = 'apx-card-loader';
    ov.innerHTML = this.markup();
    target.appendChild(ov);
  },
  hide(target) {
    if (!target) return;
    const ov = target.querySelector(':scope > .apx-card-loader');
    if (ov) ov.remove();
  }
};

const { $, $$, on, delegate, ready, create } = dom;

function init() {
  // 首屏 boot 遮罩：就绪即淡出并移除（JS 失效时 loading.css 的 fallback 也会在 6s 后自动淡出）
  const boot = document.getElementById('apx-boot');
  if (boot) {
    boot.classList.add('apx-boot--hide');
    setTimeout(() => boot.remove(), 520);
  }

  i18n.init(window.APX_I18N || {});
  theme.init();
  http.bindForms(document);

  // theme picker
  $$('[data-apx-theme-picker]').forEach((grid) => {
    grid.addEventListener('click', (e) => {
      const sw = e.target.closest('[data-theme]');
      if (!sw) return;
      theme.setTheme(sw.getAttribute('data-theme'));
      grid.querySelectorAll('.apx-theme-swatch').forEach((s) => s.classList.toggle('is-active', s === sw));
    });
  });
  // mode picker
  $$('[data-apx-mode-picker]').forEach((seg) => {
    seg.addEventListener('click', (e) => {
      const b = e.target.closest('[data-mode]');
      if (!b) return;
      theme.setMode(b.getAttribute('data-mode'));
      seg.querySelectorAll('button').forEach((x) => x.classList.toggle('is-active', x === b));
    });
  });

  // dropdown menus
  delegate(document, 'click', '[data-apx-menu-trigger]', (e, trigger) => {
    e.stopPropagation();
    const id = trigger.getAttribute('data-apx-menu-trigger');
    const menu = document.getElementById(id);
    if (!menu) return;
    const isOpen = menu.classList.contains('is-open');
    document.querySelectorAll('.apx-menu.is-open').forEach((m) => m.classList.remove('is-open'));
    if (!isOpen) menu.classList.add('is-open');
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.apx-menu.is-open').forEach((m) => m.classList.remove('is-open'));
  });

  // logout
  $$('[data-apx-logout]').forEach((el) => el.addEventListener('click', (e) => {
    e.preventDefault();
    http.post('/logout').finally(() => { location.href = http.route('/login'); });
  }));

  // start realtime if available
  if (window.APX_CONFIG && window.APX_CONFIG.realtime) realtime.start();

  highlightNav();
}

function routeOf(href) {
  if (!href) return '/home';
  const i = href.indexOf('?r=');
  if (i !== -1) {
    let rest = href.slice(i + 3);
    const amp = rest.indexOf('&');
    if (amp !== -1) rest = rest.slice(0, amp);
    return ('/' + rest).replace(/\/+$/, '') || '/home';
  }
  let p = href;
  try { p = new URL(href, location.origin).pathname; } catch {}
  p = p.replace(/\.php$/i, ''); // 实体文件地址 → 逻辑路径
  p = p.replace(/\/+$/, '');
  return p || '/home';
}

function highlightNav() {
  const cur = routeOf(location.href);
  const isActive = (href) => {
    const u = routeOf(href);
    return cur === u || (u !== '/home' && cur.indexOf(u + '/') === 0);
  };
  $$('.apx-nav__item, .apx-bottom-nav a').forEach((a) => {
    if (isActive(a.getAttribute('href'))) a.classList.add('is-active');
  });
}

ready(init);
