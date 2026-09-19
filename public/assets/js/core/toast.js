// APX · Toast notifications
import { create } from './dom.js';

let wrap;
function ensureWrap() {
  if (wrap && document.body.contains(wrap)) return wrap;
  wrap = document.createElement('div');
  wrap.className = 'apx-toast-wrap';
  document.body.appendChild(wrap);
  return wrap;
}

const ICONS = {
  success: '✓', error: '!', warning: '⚠', info: 'i',
};

// 服务端返回的 message 往往是 i18n key（如 auth.login.success），
// 这里按语言包翻译，避免把原始 key 直接显示给用户。
function tr(m) {
  if (typeof m !== 'string' || m === '') return m;
  const d = (typeof window !== 'undefined' && window.APX_I18N) || null;
  return (d && Object.prototype.hasOwnProperty.call(d, m)) ? d[m] : m;
}

export function show(type, message, opts = {}) {
  const w = ensureWrap();
  const el = create(`
    <div class="apx-toast apx-toast--${type} apx-anim-toast">
      <span class="apx-toast__icon">${ICONS[type] || 'i'}</span>
      <span class="apx-toast__msg"></span>
    </div>`);
  el.querySelector('.apx-toast__msg').textContent = tr(message);
  w.appendChild(el);
  const ttl = opts.duration ?? 3200;
  const timer = setTimeout(() => dismiss(el), ttl);
  el.addEventListener('click', () => dismiss(el));
  return () => clearTimeout(timer);
}
function dismiss(el) {
  el.style.opacity = '0';
  el.style.transform = 'translateY(-8px)';
  el.style.transition = 'opacity .2s, transform .2s';
  setTimeout(() => el.remove(), 200);
}

export const success = (m, o) => show('success', m, o);
export const error = (m, o) => show('error', m, o);
export const warning = (m, o) => show('warning', m, o);
export const info = (m, o) => show('info', m, o);

// 聚合导出：兼容 `import { toast } from '../core/toast.js'`
export const toast = { show, success, error, warning, info };
