// APX · 话题页动态流
import { initFeed } from './feed-lib.js';

const base = window.APX.base;
const topic = window.APX_TOPIC || {};
initFeed({
  container: 'apx-feed',
  varName: 'APX_POSTS',
  endpoint: base + '/api/topic/posts',
  extra: 'slug=' + encodeURIComponent(topic.slug || ''),
  emptyText: window.apx ? window.apx.i18n.t('topic.empty') : '',
});
