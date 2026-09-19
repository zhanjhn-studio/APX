// APX · Theme & appearance control (instant, no reload)
import { post } from './http.js';

const THEMES = ['graphite','minimal','light','dark','blue','purple','pink','green','gold','cyber','mint','graphite_pro'];
const MODES = ['dark','light','auto'];

function html() { return document.documentElement; }

export function current() {
  return { theme: html().getAttribute('data-apx-theme') || 'graphite', mode: html().getAttribute('data-apx-mode') || 'dark' };
}

function resolveAuto() {
  const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
  return prefers ? 'light' : 'dark';
}

export function apply(theme, mode) {
  if (theme) html().setAttribute('data-apx-theme', theme);
  if (mode) {
    const effective = mode === 'auto' ? resolveAuto() : mode;
    html().setAttribute('data-apx-mode', effective);
    html().setAttribute('data-apx-mode-pref', mode);
  }
  document.cookie = `apx_theme=${current().theme};path=/;max-age=31536000;samesite=lax`;
  document.cookie = `apx_mode=${current().mode === html().getAttribute('data-apx-mode') ? (html().getAttribute('data-apx-mode-pref')||'dark') : current().mode};path=/;max-age=31536000;samesite=lax`;
  if (window.apx && window.apx.store) window.apx.store.set('appearance', current());
}

export function setTheme(theme) { if (THEMES.includes(theme)) apply(theme, null); persist(); }
export function setMode(mode) { if (MODES.includes(mode)) apply(null, mode); persist(); }

function persist() {
  // best-effort server persistence (fire-and-forget)
  post('/api/settings/appearance', current()).catch(function () {});
}

export function init() {
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
      if ((html().getAttribute('data-apx-mode-pref') || 'dark') === 'auto') apply(null, 'auto');
    });
  }
}

export { THEMES, MODES };
