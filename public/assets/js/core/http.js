// APX · HTTP layer — unified JSON, auto CSRF, toast on error, 401 redirect.
const JSON_HEADERS = { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' };

function csrfToken() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

function refreshCsrf(newToken) {
  if (!newToken) return;
  const m = document.querySelector('meta[name="csrf-token"]');
  if (m) m.setAttribute('content', newToken);
}

function buildBody(data) {
  if (data instanceof FormData) return data;
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries(data || {})) {
    if (v === null || v === undefined) continue;
    params.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
  }
  return params.toString();
}

export class ApiError extends Error {
  constructor(payload) {
    super(payload.message || 'error');
    this.code = payload.code ?? -1;
    this.payload = payload;
    this.errors = payload.errors || {};
  }
}

/**
 * 将逻辑路径转为实际请求 URL。跟随服务端 app.pretty_urls：
 *   pretty=true  → 干净地址 /login（需服务器 try_files 转发到 index.php）
 *   pretty=false → index.php?r=/login（任意服务器零配置可用）
 */
export function route(path) {
  return routeUrl(path);
}
function routeUrl(url) {
  if (typeof url !== 'string') return url;
  if (/^https?:\/\//i.test(url)) return url;
  if (url.indexOf('?r=') !== -1) return url;
  if (/\.php(\?|#|$)/i.test(url)) return url; // 已是实体文件地址（如 /login.php），直接使用
  const cfg = window.APX_CONFIG || {};
  const base = cfg.baseUrl || '';
  const pretty = !!cfg.pretty;
  let p = url;
  if (base && p.startsWith(base)) p = p.slice(base.length);
  const qi = p.indexOf('?');
  const q = qi >= 0 ? p.slice(qi + 1) : '';
  const pathOnly = qi >= 0 ? p.slice(0, qi) : p;
  const clean = (pathOnly.startsWith('/') ? pathOnly : '/' + pathOnly);
  if (pretty) {
    return (clean === '/' ? base + '/' : base + clean) + (q ? '?' + q : '');
  }
  let out = base + 'index.php?r=' + encodeURIComponent(clean);
  if (q) out += '&' + q;
  return out;
}
window.route = route;

export async function request(method, url, data, opts = {}) {
  url = routeUrl(url);
  const headers = { ...JSON_HEADERS };
  if (method !== 'GET') headers['X-CSRF-Token'] = csrfToken();
  const init = { method, headers, credentials: 'same-origin' };
  if (method !== 'GET') init.body = data instanceof FormData ? data : buildBody(data);

  const res = await fetch(url, init);
  let json;
  try { json = await res.json(); } catch { json = { code: -1, message: 'bad_response' }; }

  // rotate csrf if returned
  if (json && json.csrf) refreshCsrf(json.csrf);

  if (res.status === 401 || (json && json.code === 401)) {
    if (window.apx && window.apx.toast) window.apx.toast.error(window.apx.i18n.t('auth.required'));
    if (!opts.silentAuth && !location.pathname.endsWith('/login')) location.href = route('/login');
    throw new ApiError(json);
  }
  if (json && json.code === 0) return json;
  throw new ApiError(json || { code: res.status, message: res.statusText });
}

export const get = (url, opts) => request('GET', url, null, opts);
export const post = (url, data, opts) => request('POST', url, data, opts);
export const postForm = (url, formEl, opts) => request('POST', url, new FormData(formEl), opts);

// Auto-bind any form with [data-apx-submit] to POST its action; reads data-success-redirect.
export function bindForms(root = document) {
  root.querySelectorAll('form[data-apx-submit]').forEach((form) => {
    if (form.dataset.bound) return;
    form.dataset.bound = '1';
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"], [data-submit]');
      const url = form.getAttribute('action') || location.pathname;
      try {
        if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
        clearErrors(form);
        const json = await postForm(url, form);
        const redirect = form.getAttribute('data-success-redirect') || (json.data && json.data.redirect);
        if (window.apx && window.apx.toast) window.apx.toast.success(json.message);
        if (redirect) { setTimeout(() => { location.href = redirect; }, 420); return; }
        if (form.getAttribute('data-reload') !== null) location.reload();
      } catch (err) {
        if (err instanceof ApiError) {
          if (err.errors && Object.keys(err.errors).length) showErrors(form, err.errors);
          else if (window.apx && window.apx.toast) window.apx.toast.error(err.message);
        }
      } finally {
        if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; }
      }
    });
  });
}

function showErrors(form, errors) {
  for (const [field, msg] of Object.entries(errors)) {
    const input = form.querySelector(`[name="${field}"]`);
    if (!input) continue;
    const fieldEl = input.closest('.apx-field') || input.parentElement;
    if (fieldEl) fieldEl.classList.add('has-error');
    const errEl = fieldEl ? fieldEl.querySelector('.apx-field__error') : null;
    if (errEl) errEl.textContent = msg;
  }
}
function clearErrors(form) {
  form.querySelectorAll('.has-error').forEach((el) => el.classList.remove('has-error'));
}
