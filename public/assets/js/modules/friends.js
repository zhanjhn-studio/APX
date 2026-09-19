import { post } from '../core/http.js';
import { toast } from '../core/toast.js';
import { $$, delegate } from '../core/dom.js';

const base = window.APX_CONFIG?.baseUrl || '';
const t = (k, p) => (window.apx ? window.apx.i18n.t(k, p) : k);

function init() {
  const tabs = document.getElementById('apx-friend-tabs');
  if (!tabs) return;

  tabs.addEventListener('click', (e) => {
    const tab = e.target.closest('.apx-tab');
    if (!tab) return;
    const name = tab.dataset.tab;
    $$('.apx-tab').forEach((x) => x.classList.toggle('is-active', x === tab));
    $$('.apx-tabpanel').forEach((p) => p.classList.toggle('is-active', p.dataset.panel === name));
  });

  const root = document.querySelector('.apx-friends');
  if (!root) return;
  root.addEventListener('click', (e) => {
    const accept = e.target.closest('[data-accept]');
    const decline = e.target.closest('[data-decline]');
    const special = e.target.closest('[data-special]');
    const remove = e.target.closest('[data-remove]');
    const message = e.target.closest('[data-message]');
    const follow = e.target.closest('[data-follow]');
    if (accept) return respond(accept, 'accept');
    if (decline) return respond(decline, 'decline');
    if (special) return toggleSpecial(special);
    if (remove) return removeFriend(remove);
    if (message) return startChat(message);
    if (follow) return toggleFollow(follow);
  });
}

async function respond(btn, action) {
  const id = btn.dataset.accept || btn.dataset.decline;
  try {
    await post(base + '/api/friend/respond', { request_id: id, action });
    const row = btn.closest('.apx-user-row');
    if (row) row.remove();
    toast.success(t('common.done'));
  } catch (e) { /* ignore */ }
}

async function toggleSpecial(btn) {
  const on = btn.dataset.on === '1' ? 0 : 1;
  try {
    await post(base + '/api/friend/special', { user_id: btn.dataset.special, on });
    btn.dataset.on = String(on);
    btn.textContent = t('friends.special') + (on ? ' ✓' : '');
    toast.success(t('common.done'));
  } catch (e) { /* ignore */ }
}

async function removeFriend(btn) {
  try {
    await post(base + '/api/friend/remove', { user_id: btn.dataset.remove });
    const row = btn.closest('.apx-user-row');
    if (row) row.remove();
    toast.success(t('common.done'));
  } catch (e) { /* ignore */ }
}

async function startChat(btn) {
  try {
    const j = await post(base + '/api/message/start', { user_id: btn.dataset.message });
    if (j && j.data && j.data.redirect) location.href = j.data.redirect;
  } catch (e) { /* ignore */ }
}

async function toggleFollow(btn) {
  try {
    const j = await post(base + '/api/follow', { user_id: btn.dataset.follow });
    const on = j && j.data ? !!j.data.following : false;
    btn.classList.toggle('apx-btn--primary', on);
    btn.classList.toggle('apx-btn--soft', !on);
    btn.textContent = t(on ? 'post.following' : 'post.follow');
  } catch (e) { /* ignore */ }
}

if (document.readyState !== 'loading') init();
else document.addEventListener('DOMContentLoaded', init);
