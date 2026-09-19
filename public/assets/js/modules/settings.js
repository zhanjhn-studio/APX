// APX · 设置中心：外观 / 安全 / 设备 / 隐私 / 免打扰 / 注销
import { $, $$, delegate, ready, escapeHtml } from '../core/dom.js';
import * as http from '../core/http.js';
import * as modal from '../core/modal.js';
import { toast } from '../core/toast.js';

const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);

/* ---------------- 分区切换 ---------------- */
function initSections() {
  const nav = document.querySelector('.apx-settings__nav');
  if (!nav) return;
  nav.addEventListener('click', (e) => {
    const item = e.target.closest('.apx-settings__nav-item');
    if (!item) return;
    const key = item.getAttribute('data-sec');
    nav.querySelectorAll('.apx-settings__nav-item').forEach((x) => x.classList.toggle('is-active', x === item));
    $$('.apx-settings__sec').forEach((s) => s.classList.toggle('is-active', s.getAttribute('data-sec') === key));
  });
}

/* ---------------- 两步验证 ---------------- */
function showBackupCodes(codes) {
  const box = document.getElementById('apx-totp-codes');
  const grid = document.getElementById('apx-totp-codes-grid');
  if (!box || !grid) return;
  grid.innerHTML = codes.map((c) => `<code>${escapeHtml(c)}</code>`).join('');
  box.hidden = false;
}

function initTotp() {
  const setup = document.getElementById('apx-totp-setup');
  if (setup) setup.addEventListener('click', async () => {
    setup.disabled = true;
    try {
      const res = await http.post('/api/settings/totp/setup', {});
      const d = res.data || {};
      document.getElementById('apx-totp-secret').textContent = d.secret || '';
      document.getElementById('apx-totp-uri').textContent = d.uri || '';
      document.getElementById('apx-totp-setup-box').hidden = false;
      setup.hidden = true;
    } catch (err) { toast.error(err.message); }
    finally { setup.disabled = false; }
  });

  const copy = document.getElementById('apx-totp-copy');
  if (copy) copy.addEventListener('click', () => {
    const text = document.getElementById('apx-totp-secret').textContent || '';
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(() => toast.success(t('common.done')));
  });

  const confirm = document.getElementById('apx-totp-confirm');
  if (confirm) confirm.addEventListener('click', async () => {
    const code = (document.getElementById('apx-totp-code').value || '').trim();
    if (!code) { toast.error(t('validation.required')); return; }
    confirm.disabled = true;
    try {
      const res = await http.post('/api/settings/totp/enable', { code });
      toast.success(res.message);
      showBackupCodes((res.data && res.data.codes) || []);
      document.getElementById('apx-totp-setup-box').hidden = true;
      document.getElementById('apx-totp-off').hidden = true;
      document.getElementById('apx-totp-on').hidden = false;
      const st = document.getElementById('apx-totp-state');
      if (st) { st.textContent = t('settings.totp.on'); st.classList.add('apx-badge--success'); }
    } catch (err) { toast.error(err.message); }
    finally { confirm.disabled = false; }
  });

  const disableOpen = document.getElementById('apx-totp-disable-open');
  if (disableOpen) disableOpen.addEventListener('click', () => {
    const box = document.getElementById('apx-totp-disable-box');
    if (box) box.hidden = !box.hidden;
  });

  const disable = document.getElementById('apx-totp-disable');
  if (disable) disable.addEventListener('click', async () => {
    const code = (document.getElementById('apx-totp-disable-code').value || '').trim();
    if (!code) { toast.error(t('validation.required')); return; }
    disable.disabled = true;
    try {
      const res = await http.post('/api/settings/totp/disable', { code });
      toast.success(res.message);
      setTimeout(() => location.reload(), 400);
    } catch (err) { toast.error(err.message); }
    finally { disable.disabled = false; }
  });

  const regen = document.getElementById('apx-totp-regen');
  if (regen) regen.addEventListener('click', async () => {
    const ok = await modal.confirm({ body: t('settings.totp.regen_confirm'), danger: true, confirmText: t('common.action.confirm'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/settings/totp/backup', {});
      toast.success(res.message);
      showBackupCodes((res.data && res.data.codes) || []);
      const cnt = document.getElementById('apx-totp-backup-count');
      if (cnt) cnt.textContent = String((res.data && res.data.codes || []).length);
    } catch (err) { toast.error(err.message); }
  });
}

/* ---------------- 设备 ---------------- */
function initDevices() {
  delegate(document, 'click', '[data-device-trust]', async (e, btn) => {
    e.preventDefault();
    const id = btn.getAttribute('data-device-trust');
    const trusted = btn.getAttribute('data-trusted') === '1' ? 0 : 1;
    try {
      await http.post('/api/settings/device/trust', { id, trusted });
      toast.success(t('settings.device_updated'));
      setTimeout(() => location.reload(), 320);
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-device-revoke]', async (e, btn) => {
    e.preventDefault();
    const id = btn.getAttribute('data-device-revoke');
    const ok = await modal.confirm({ body: t('settings.device_revoke_confirm'), danger: true, confirmText: t('settings.device_revoke'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/settings/device/revoke', { id });
      if (res.data && res.data.redirect) { location.href = res.data.redirect; return; }
      toast.success(res.message);
      setTimeout(() => location.reload(), 320);
    } catch (err) { toast.error(err.message); }
  });

  const all = document.getElementById('apx-device-revoke-others');
  if (all) all.addEventListener('click', async () => {
    const ok = await modal.confirm({ body: t('settings.devices_revoke_others_confirm'), danger: true, confirmText: t('common.action.confirm'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/settings/device/revoke-others', {});
      toast.success(res.message);
      setTimeout(() => location.reload(), 400);
    } catch (err) { toast.error(err.message); }
  });
}

/* ---------------- 隐私 ---------------- */
function userRow(u, actionAttr, label) {
  return `<div class="apx-user-row" data-user="${u.id}">
    <span class="apx-avatar apx-avatar--md">${escapeHtml(String(u.nickname || u.username || '?').slice(0, 1))}</span>
    <div class="apx-user-row__body">
      <div class="apx-user-row__name">${escapeHtml(u.nickname || u.username || '')}</div>
      <div class="apx-user-row__sub">@${escapeHtml(u.username || '')}</div>
    </div>
    <div class="apx-user-row__actions">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" ${actionAttr}="${u.id}">${escapeHtml(label)}</button>
    </div>
  </div>`;
}

function initPrivacy() {
  const wordAdd = document.getElementById('apx-word-add');
  if (wordAdd) wordAdd.addEventListener('click', async () => {
    const input = document.getElementById('apx-word-input');
    const word = input.value.trim();
    if (!word) return;
    try {
      const res = await http.post('/api/settings/word', { word });
      const list = document.getElementById('apx-word-list');
      const empty = document.getElementById('apx-word-empty');
      if (empty) empty.remove();
      const saved = ((res.data && res.data.words) || []).find((w) => w.word === word);
      const key = saved ? saved.id : word;
      list.insertAdjacentHTML('beforeend', `<span class="apx-chip" data-word="${key}">${escapeHtml(word)}<button data-word-remove="${key}" aria-label="remove">&times;</button></span>`);
      input.value = '';
      toast.success(res.message);
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-word-remove]', async (e, btn) => {
    e.preventDefault();
    const key = btn.getAttribute('data-word-remove');
    const chip = btn.closest('.apx-chip');
    const payload = /^\d+$/.test(key) ? { id: key } : { word: key };
    try {
      await http.post('/api/settings/word/remove', payload);
      if (chip) chip.remove();
    } catch (err) { toast.error(err.message); }
  });

  const muteAdd = document.getElementById('apx-mute-add');
  if (muteAdd) muteAdd.addEventListener('click', async () => {
    const input = document.getElementById('apx-mute-input');
    const username = input.value.trim();
    if (!username) return;
    try {
      const res = await http.post('/api/settings/mute', { username });
      const list = document.getElementById('apx-mute-list');
      const u = (res.data && res.data.muted || []).find((x) => x.username === username);
      if (u && list) list.insertAdjacentHTML('afterbegin', userRow(u, 'data-mute-remove', t('common.action.delete')));
      input.value = '';
      toast.success(res.message);
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-mute-remove]', async (e, btn) => {
    e.preventDefault();
    try {
      await http.post('/api/settings/mute/remove', { user_id: btn.getAttribute('data-mute-remove') });
      const row = btn.closest('.apx-user-row');
      if (row) row.remove();
    } catch (err) { toast.error(err.message); }
  });

  const blockAdd = document.getElementById('apx-block-add');
  if (blockAdd) blockAdd.addEventListener('click', async () => {
    const input = document.getElementById('apx-block-input');
    const username = input.value.trim();
    if (!username) return;
    try {
      const res = await http.post('/api/settings/block', { username });
      const list = document.getElementById('apx-block-list');
      const u = (res.data && res.data.blocked || []).find((x) => x.username === username);
      if (u && list) list.insertAdjacentHTML('afterbegin', userRow(u, 'data-block-remove', t('settings.unblock')));
      input.value = '';
      toast.success(res.message);
    } catch (err) { toast.error(err.message); }
  });

  delegate(document, 'click', '[data-block-remove]', async (e, btn) => {
    e.preventDefault();
    try {
      await http.post('/api/settings/block/remove', { user_id: btn.getAttribute('data-block-remove') });
      const row = btn.closest('.apx-user-row');
      if (row) row.remove();
    } catch (err) { toast.error(err.message); }
  });
}

/* ---------------- 免打扰 ---------------- */
function initDnd() {
  const save = document.getElementById('apx-dnd-save');
  if (!save) return;
  save.addEventListener('click', async () => {
    save.disabled = true;
    try {
      const res = await http.post('/api/settings/dnd', {
        enabled: document.getElementById('apx-dnd-enabled').checked ? 1 : 0,
        start: document.getElementById('apx-dnd-start').value || '22:00',
        end: document.getElementById('apx-dnd-end').value || '08:00',
      });
      toast.success(res.message);
    } catch (err) { toast.error(err.message); }
    finally { save.disabled = false; }
  });
}

/* ---------------- 账号注销 ---------------- */
function initAccount() {
  const del = document.getElementById('apx-account-delete');
  if (del) del.addEventListener('click', async () => {
    const password = (document.getElementById('apx-delete-password').value || '');
    if (!password) { toast.error(t('validation.required')); return; }
    const ok = await modal.confirm({ body: t('settings.delete.confirm'), danger: true, confirmText: t('settings.delete.submit'), cancelText: t('common.cancel') });
    if (!ok) return;
    del.disabled = true;
    try {
      const res = await http.post('/api/settings/account/delete', { password });
      toast.success(res.message);
      const redirect = (res.data && res.data.redirect) || http.route('/login');
      setTimeout(() => { location.href = redirect; }, 600);
    } catch (err) { toast.error(err.message); del.disabled = false; }
  });

  const cancel = document.getElementById('apx-account-cancel-delete');
  if (cancel) cancel.addEventListener('click', async () => {
    try {
      const res = await http.post('/api/settings/account/cancel-delete', {});
      toast.success(res.message);
      setTimeout(() => location.reload(), 400);
    } catch (err) { toast.error(err.message); }
  });
}

ready(() => {
  initSections();
  initTotp();
  initDevices();
  initPrivacy();
  initDnd();
  initAccount();
});
