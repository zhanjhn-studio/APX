// APX · 个人主页动态流 + 拉黑
import { initFeed } from './feed-lib.js';
import { delegate, ready } from '../core/dom.js';
import * as http from '../core/http.js';
import * as modal from '../core/modal.js';
import { toast } from '../core/toast.js';

const base = window.APX.base;
const profile = window.APX_PROFILE || {};
initFeed({
  container: 'apx-feed',
  varName: 'APX_POSTS',
  endpoint: base + '/api/profile/posts',
  extra: 'username=' + encodeURIComponent(profile.username || ''),
  emptyText: window.apx ? window.apx.i18n.t('profile.posts_empty') : '',
});

ready(() => {
  const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);
  delegate(document, 'click', '[data-profile-block]', async (e, btn) => {
    e.preventDefault();
    const ok = await modal.confirm({ body: t('profile.block_confirm'), danger: true, confirmText: t('settings.block'), cancelText: t('common.cancel') });
    if (!ok) return;
    try {
      const res = await http.post('/api/settings/block', { user_id: btn.getAttribute('data-profile-block') });
      toast.success(res.message);
      btn.remove();
    } catch (err) { toast.error(err.message); }
  });
});
