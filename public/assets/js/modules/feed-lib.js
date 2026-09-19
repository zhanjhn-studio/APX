// APX · 通用动态流加载器（复用 postcard.js 的 renderPost / bindGlobal）。
import { get } from '../core/http.js';
import { t } from '../core/i18n.js';
import { renderPost, bindGlobal } from './postcard.js';

/**
 * opts: { container, varName (window 上的初始数组), endpoint, extra (附加 query), renderItem, pick, emptyText }
 */
export function initFeed(opts) {
  bindGlobal();
  const feed = typeof opts.container === 'string' ? document.getElementById(opts.container) : opts.container;
  if (!feed) return;
  const initial = window[opts.varName] || [];
  const endpoint = opts.endpoint;
  const render = opts.renderItem || renderPost;
  let oldest = 0;
  let loading = false;
  let hasMore = true;

  const append = (items) => {
    if (!items || !items.length) {
      if (!feed.children.length) {
        feed.innerHTML = `<div class="apx-card apx-empty"><div class="apx-empty__title">${opts.emptyText || t('common.empty')}</div></div>`;
      }
      hasMore = false;
      return;
    }
    items.forEach((it) => {
      const item = opts.pick ? opts.pick(it) : it;
      if (!item) return;
      feed.appendChild(render(item));
      const id = parseInt(item.id, 10);
      if (!oldest || id < oldest) oldest = id;
    });
    if (items.length < (opts.limit || 20)) hasMore = false;
  };

  append(initial);

  const loadMore = async () => {
    if (loading || !hasMore) return;
    loading = true;
    try {
      const url = endpoint + '?before=' + oldest + (opts.extra ? '&' + opts.extra : '');
      const j = await get(url);
      if (j && j.code === 0) {
        append(j.data.items || []);
        hasMore = !!j.data.has_more;
      } else {
        hasMore = false;
      }
    } catch (e) {
      hasMore = false;
    }
    loading = false;
  };

  window.addEventListener('scroll', () => {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 700) loadMore();
  });
  if (document.body.offsetHeight < window.innerHeight + 200) loadMore();
}
