// APX · 发现页动态流
import { initFeed } from './feed-lib.js';

const base = window.APX.base;
initFeed({
  container: 'apx-feed',
  varName: 'APX_POSTS',
  endpoint: base + '/api/discover/feed',
  emptyText: window.apx ? window.apx.i18n.t('discover.empty') : '',
});
