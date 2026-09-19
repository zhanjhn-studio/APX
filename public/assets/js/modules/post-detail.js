import { renderPost, bindGlobal } from './postcard.js';

bindGlobal();
const post = window.APX_POST;
const root = document.getElementById('apx-post-detail');
if (post && root) {
  root.appendChild(renderPost(post, { detail: true }));
}
