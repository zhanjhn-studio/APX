// APX · Modal dialog
import { create, $ } from './dom.js';

let overlay;
function ensureOverlay() {
  if (overlay && document.body.contains(overlay)) return overlay;
  overlay = create('<div class="apx-modal-overlay"></div>');
  document.body.appendChild(overlay);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && overlay.classList.contains('is-open')) close(); });
  return overlay;
}

export function open(content, opts = {}) {
  const ov = ensureOverlay();
  const node = typeof content === 'string' ? create(`<div class="apx-modal apx-anim-pop">${content}</div>`) : content;
  ov.innerHTML = '';
  ov.appendChild(node);
  ov.classList.add('is-open');
  document.body.style.overflow = 'hidden';
  const closeBtn = node.querySelector('[data-modal-close]');
  if (closeBtn) closeBtn.addEventListener('click', close);
  if (opts.onOpen) opts.onOpen(node);
  return node;
}

export function close() {
  if (!overlay) return;
  overlay.classList.remove('is-open');
  document.body.style.overflow = '';
  setTimeout(() => { if (overlay) overlay.innerHTML = ''; }, 220);
}

// Convenience confirm dialog returning a Promise<boolean>
export function confirm({ title = '', body = '', confirmText = '确认', cancelText = '取消', danger = false } = {}) {
  return new Promise((resolve) => {
    const node = open(`
      <div class="apx-modal__title">${title}</div>
      <div class="apx-modal__body">${body}</div>
      <div class="apx-modal__foot">
        <button class="apx-btn apx-btn--ghost apx-btn--sm" data-cancel>${cancelText}</button>
        <button class="apx-btn ${danger ? 'apx-btn--danger' : 'apx-btn--primary'} apx-btn--sm" data-ok>${confirmText}</button>
      </div>`);
    node.querySelector('[data-cancel]').addEventListener('click', () => { close(); resolve(false); });
    node.querySelector('[data-ok]').addEventListener('click', () => { close(); resolve(true); });
  });
}
