// APX · 收藏夹：动态流 + 收藏夹管理与分类
import { get, post } from '../core/http.js';
import { t } from '../core/i18n.js';
import { renderPost, bindGlobal } from './postcard.js';

const base = window.APX.base;
const folders = window.APX_FOLDERS || [];
const activeFolder = window.APX_ACTIVE_FOLDER || null;

function folderOptions(targetId) {
  const opts = ['<option value="">' + t('favorite.default') + '</option>']
    .concat(folders.map((f) => '<option value="' + f.id + '">' + f.name + '</option>'));
  return `<select class="apx-select apx-fav-folder" data-target="${targetId}">${opts.join('')}</select>`;
}

function renderFav(it) {
  const p = it.post;
  if (!p) return null;
  const wrap = document.createElement('div');
  wrap.className = 'apx-fav-item';
  wrap.appendChild(renderPost(p));
  const bar = document.createElement('div');
  bar.className = 'apx-fav-item__bar';
  bar.innerHTML = `<span class="apx-text-faint apx-mt-2" style="font-size:12px;">${t('favorite.move')}</span> ` + folderOptions(p.id);
  wrap.appendChild(bar);
  return wrap;
}

function initFeed() {
  bindGlobal();
  const feed = document.getElementById('apx-feed');
  if (!feed) return;
  const initial = window.APX_FAVS || [];
  let oldest = 0, loading = false, hasMore = true;

  const append = (items) => {
    if (!items || !items.length) {
      if (!feed.children.length) {
        feed.innerHTML = `<div class="apx-card apx-empty"><div class="apx-empty__title">${t('favorite.empty')}</div></div>`;
      }
      hasMore = false;
      return;
    }
    items.forEach((it) => {
      const el = renderFav(it);
      if (!el) return;
      feed.appendChild(el);
      const pid = parseInt((it.post && it.post.id) || 0, 10);
      if (pid && (!oldest || pid < oldest)) oldest = pid;
    });
    if (items.length < 20) hasMore = false;
  };
  append(initial);

  const loadMore = async () => {
    if (loading || !hasMore) return;
    loading = true;
    try {
      const url = base + '/api/favorites?before=' + oldest + (activeFolder ? '&folder=' + activeFolder : '');
      const j = await get(url);
      if (j && j.code === 0) { append(j.data.items || []); hasMore = !!j.data.has_more; }
      else hasMore = false;
    } catch (e) { hasMore = false; }
    loading = false;
  };
  window.addEventListener('scroll', () => {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 700) loadMore();
  });
  if (document.body.offsetHeight < window.innerHeight + 200) loadMore();

  feed.addEventListener('change', async (e) => {
    const sel = e.target.closest('.apx-fav-folder');
    if (!sel) return;
    const targetId = sel.getAttribute('data-target');
    try {
      const j = await post(base + '/api/favorites/toggle', { target_id: targetId, folder_id: sel.value || '' });
      if (j && j.code === 0) { /* 已更新收藏夹 */ }
    } catch (err) {}
  });
}

function initFolderActions() {
  const addBtn = document.getElementById('apx-folder-add');
  if (addBtn) {
    addBtn.addEventListener('click', async () => {
      const name = window.prompt(t('favorite.folder_name'));
      if (!name) return;
      try {
        const j = await post(base + '/api/favorites/folder', { name });
        if (j && j.code === 0) location.reload();
      } catch (e) {}
    });
  }
  document.querySelectorAll('[data-folder-rename]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-folder-rename');
      const name = window.prompt(t('favorite.rename'), btn.getAttribute('data-name') || '');
      if (!name) return;
      await post(base + '/api/favorites/folder/rename', { folder_id: id, name });
      location.reload();
    });
  });
  document.querySelectorAll('[data-folder-delete]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-folder-delete');
      if (!window.confirm(t('favorite.confirm_delete'))) return;
      await post(base + '/api/favorites/folder/delete', { folder_id: id });
      location.reload();
    });
  });
}

initFeed();
initFolderActions();
