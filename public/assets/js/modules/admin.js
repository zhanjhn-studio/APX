// APX · 后台交互（用户 / 内容 / 群组 / 举报 / 角色 / 主题语言 / 设置 / WAF / 更新）
import { $, $$, delegate, ready, escapeHtml } from '../core/dom.js';
import * as http from '../core/http.js';
import * as modal from '../core/modal.js';
import { toast } from '../core/toast.js';

const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);

// 危险操作的二次确认文案（显式映射，避免动态拼接 key 造成漏翻译）
const USER_CONFIRM = {
  ban: 'admin.user.confirm_ban',
  revoke_super: 'admin.user.confirm_revoke_super',
};
const REPORT_CONFIRM = {
  delete: 'admin.report.confirm_delete',
  ban: 'admin.report.confirm_ban',
};

function reload(delay = 420) {
  setTimeout(() => location.reload(), delay);
}

async function act(url, payload, confirmBody) {
  if (confirmBody) {
    const ok = await modal.confirm({
      body: confirmBody,
      danger: true,
      confirmText: t('common.action.confirm'),
      cancelText: t('common.action.cancel'),
    });
    if (!ok) return false;
  }
  try {
    const res = await http.post(url, payload);
    toast.success(res.message);
    return true;
  } catch (err) {
    toast.error(err.message);
    return false;
  }
}

ready(() => {
  /* ---------- 用户管理 ---------- */
  delegate(document, 'click', '[data-user-action]', async (e, btn) => {
    e.preventDefault();
    const action = btn.getAttribute('data-user-action');
    const payload = { user_id: btn.getAttribute('data-user'), action };
    const roleId = btn.getAttribute('data-role');
    if (roleId) payload.role_id = roleId;
    const ok = await act('/admin/users/action', payload, USER_CONFIRM[action] ? t(USER_CONFIRM[action]) : null);
    if (ok) reload();
  });

  /* ---------- 内容管理 ---------- */
  delegate(document, 'click', '[data-post-action]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/posts/action', {
      post_id: btn.getAttribute('data-post'),
      action: btn.getAttribute('data-post-action'),
    }, t('post.confirm_delete'));
    if (ok) reload();
  });
  delegate(document, 'click', '[data-comment-action]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/comments/action', {
      comment_id: btn.getAttribute('data-comment'),
      action: btn.getAttribute('data-comment-action'),
    }, t('post.confirm_delete'));
    if (ok) reload();
  });

  /* ---------- 群组管理 ---------- */
  delegate(document, 'click', '[data-group-action]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/groups/action', {
      group_id: btn.getAttribute('data-group'),
      action: btn.getAttribute('data-group-action'),
    }, t('group.confirm_disband'));
    if (ok) reload();
  });

  /* ---------- 黑名单 ---------- */
  delegate(document, 'click', '[data-block-remove]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/blocked/action', { id: btn.getAttribute('data-block-remove') }, t('admin.blocked.confirm_remove'));
    if (ok) {
      const row = btn.closest('tr');
      if (row) row.remove();
    }
  });

  /* ---------- 举报处理 ---------- */
  delegate(document, 'click', '[data-report-action]', async (e, btn) => {
    e.preventDefault();
    const card = btn.closest('.apx-report');
    const note = card ? (card.querySelector('.apx-report__note') || {}).value || '' : '';
    const action = btn.getAttribute('data-report-action');
    const ok = await act('/admin/reports/action', {
      report_id: btn.getAttribute('data-report'),
      action,
      note,
    }, REPORT_CONFIRM[action] ? t(REPORT_CONFIRM[action]) : null);
    if (ok) reload();
  });

  /* ---------- 角色与权限 ---------- */
  const permForm = document.getElementById('apx-role-perms');
  if (permForm) {
    permForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const ids = $$('input[name="permissions[]"]:checked', permForm).map((i) => i.value);
      const params = new URLSearchParams();
      params.append('role_id', permForm.getAttribute('data-role'));
      ids.forEach((id) => params.append('permissions[]', id));
      try {
        const res = await http.request('POST', '/admin/roles/save', params);
        toast.success(res.message);
      } catch (err) { toast.error(err.message); }
    });
  }
  delegate(document, 'click', '[data-perm-all]', (e, btn) => {
    e.preventDefault();
    const group = btn.getAttribute('data-perm-all');
    const labels = $$(`.apx-perm[data-group="${group}"] input`);
    const allOn = labels.every((i) => i.checked);
    labels.forEach((i) => { i.checked = !allOn; });
  });

  const roleCreate = document.getElementById('apx-role-create');
  if (roleCreate) {
    roleCreate.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        const res = await http.post('/admin/roles/create', {
          name: roleCreate.querySelector('[name="name"]').value,
          slug: roleCreate.querySelector('[name="slug"]').value,
          description: roleCreate.querySelector('[name="description"]').value,
        });
        toast.success(res.message);
        reload();
      } catch (err) { toast.error(err.message); }
    });
  }
  delegate(document, 'click', '[data-role-delete]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/roles/delete', { role_id: btn.getAttribute('data-role-delete') }, t('common.action.delete') + '?');
    if (ok) reload();
  });

  /* ---------- 主题与语言 ---------- */
  delegate(document, 'change', '[data-theme-toggle]', async (e, cb) => {
    const ok = await act('/admin/appearance/save', { action: 'theme_toggle', id: cb.getAttribute('data-theme-toggle'), on: cb.checked ? 1 : 0 });
    if (!ok) cb.checked = !cb.checked;
  });
  delegate(document, 'click', '[data-theme-default]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/appearance/save', { action: 'theme_default', code: btn.getAttribute('data-theme-default') });
    if (ok) reload();
  });
  delegate(document, 'change', '[data-lang-toggle]', async (e, cb) => {
    const ok = await act('/admin/appearance/save', { action: 'lang_toggle', id: cb.getAttribute('data-lang-toggle'), on: cb.checked ? 1 : 0 });
    if (!ok) cb.checked = !cb.checked;
  });
  delegate(document, 'click', '[data-lang-default]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/appearance/save', { action: 'lang_default', code: btn.getAttribute('data-lang-default') });
    if (ok) reload();
  });

  /* ---------- 站点设置 ---------- */
  const siteForm = document.getElementById('apx-site-settings');
  if (siteForm) {
    siteForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const data = {};
      siteForm.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
        if (el.type === 'checkbox') { data[el.name] = el.checked ? 1 : 0; } else { data[el.name] = el.value; }
      });
      try {
        const res = await http.post('/admin/settings/save', data);
        toast.success(res.message);
      } catch (err) { toast.error(err.message); }
    });
  }

  /* ---------- WAF ---------- */
  delegate(document, 'change', '[data-rule-toggle]', async (e, cb) => {
    try {
      await http.post('/admin/waf/rule/toggle', { id: cb.getAttribute('data-rule-toggle'), enabled: cb.checked ? 1 : 0 });
      toast.success(cb.checked ? t('admin.waf.toggled_on') : t('admin.waf.toggled_off'));
    } catch (err) { cb.checked = !cb.checked; toast.error(err.message); }
  });
  delegate(document, 'click', '[data-unban]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/waf/unban', { ip: btn.getAttribute('data-unban') }, t('admin.waf.unban') + '?');
    if (ok) reload();
  });
  delegate(document, 'change', '[data-waf-mode]', async (e, cb) => {
    const mode = cb.checked ? 'defense' : 'observe';
    const ok = await act('/admin/waf/mode', { mode });
    if (!ok) cb.checked = !cb.checked;
    else reload();
  });

  /* ---------- 更新 ---------- */
  delegate(document, 'click', '[data-update-download]', async (e, btn) => {
    e.preventDefault();
    const ok = await act('/admin/update/download', { url: btn.getAttribute('data-update-download') }, t('admin.update.download_confirm'));
    if (ok) reload();
  });
  delegate(document, 'click', '[data-update-migrate]', async (e, btn) => {
    e.preventDefault();
    btn.disabled = true;
    const ok = await act('/admin/update/migrate', {}, t('admin.update.migration_confirm'));
    if (ok) reload();
    else btn.disabled = false;
  });
});
