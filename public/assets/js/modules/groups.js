// APX · 群组广场：我的群组 / 发现、创建群组、加入群组
import { $, $$, delegate, ready } from '../core/dom.js';
import * as http from '../core/http.js';
import * as modal from '../core/modal.js';
import { toast } from '../core/toast.js';

const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);

function initTabs() {
  const wrap = document.getElementById('apx-group-tabs');
  if (!wrap) return;
  wrap.addEventListener('click', (e) => {
    const tab = e.target.closest('.apx-tab');
    if (!tab) return;
    const key = tab.getAttribute('data-tab');
    wrap.querySelectorAll('.apx-tab').forEach((x) => x.classList.toggle('is-active', x === tab));
    $$('.apx-tabpanel').forEach((p) => p.classList.toggle('is-active', p.getAttribute('data-panel') === key));
  });
}

function createDialog() {
  const node = modal.open(`
    <div class="apx-modal__title">${t('group.create')}</div>
    <div class="apx-field">
      <span class="apx-field__label">${t('group.manage.name')}</span>
      <input class="apx-input" name="name" maxlength="64" placeholder="${t('group.name_placeholder')}">
    </div>
    <div class="apx-field">
      <span class="apx-field__label">${t('group.manage.desc')}</span>
      <input class="apx-input" name="description" maxlength="255" placeholder="${t('group.desc_placeholder')}">
    </div>
    <div class="apx-field">
      <span class="apx-field__label">${t('group.manage.visibility')}</span>
      <select class="apx-input" name="visibility">
        <option value="public">${t('group.visibility.public')}</option>
        <option value="private">${t('group.visibility.private')}</option>
        <option value="hidden">${t('group.visibility.hidden')}</option>
      </select>
    </div>
    <div class="apx-modal__foot">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-modal-close>${t('common.cancel')}</button>
      <button class="apx-btn apx-btn--primary apx-btn--sm" data-create>${t('group.create')}</button>
    </div>`);

  const btn = node.querySelector('[data-create]');
  btn.addEventListener('click', async () => {
    const name = node.querySelector('[name="name"]').value.trim();
    if (!name) { toast.error(t('validation.required')); return; }
    btn.classList.add('is-loading');
    btn.disabled = true;
    try {
      const res = await http.post('/api/group/create', {
        name,
        description: node.querySelector('[name="description"]').value.trim(),
        visibility: node.querySelector('[name="visibility"]').value,
      });
      toast.success(res.message);
      const redirect = (res.data && res.data.redirect) || http.route('/groups');
      setTimeout(() => { location.href = redirect; }, 400);
    } catch (err) {
      if (window.apx && window.apx.toast) window.apx.toast.error(err.message);
    } finally {
      btn.classList.remove('is-loading');
      btn.disabled = false;
    }
  });
}

async function joinGroup(id, btn) {
  btn.disabled = true;
  btn.classList.add('is-loading');
  try {
    const res = await http.post('/api/group/join', { group_id: id });
    toast.success(res.message);
    const status = res.data && res.data.status;
    if (status === 'joined') {
      const slug = btn.getAttribute('data-slug');
      if (slug) { setTimeout(() => { location.href = http.route('/group/' + slug); }, 380); return; }
      setTimeout(() => location.reload(), 380);
    } else {
      btn.outerHTML = `<span class="apx-pill">${t('group.pending')}</span>`;
    }
  } catch (err) {
    if (window.apx && window.apx.toast) window.apx.toast.error(err.message);
    btn.disabled = false;
    btn.classList.remove('is-loading');
  }
}

ready(() => {
  initTabs();
  const open = document.getElementById('apx-group-create-open');
  if (open) open.addEventListener('click', createDialog);

  delegate(document, 'click', '[data-group-join]', (e, btn) => {
    e.preventDefault();
    joinGroup(btn.getAttribute('data-group-join'), btn);
  });
});
