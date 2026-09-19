// APX · Rich text rendering (client side). Mirrors App\Core\LinkRenderer.
// Escapes text, then links URLs / #topics# / @mentions as safe anchors.
import { escapeHtml } from '../core/dom.js';

const base = (window.APX_CONFIG && window.APX_CONFIG.baseUrl) || '';

function isInternal(url) {
  try {
    const u = new URL(url);
    return u.origin === window.location.origin;
  } catch (e) {
    return false;
  }
}

function urlAnchor(url) {
  const safe = escapeHtml(url);
  if (isInternal(url)) {
    return `<a href="${safe}" class="apx-link-internal" data-internal="1">${safe}</a>`;
  }
  return `<a href="${safe}" class="apx-link-external" data-external="1" data-url="${safe}" target="_blank" rel="noopener noreferrer nofollow">${safe}</a>`;
}

function topicAnchor(token) {
  const name = token.slice(1, -1);
  if (!name) return escapeHtml(token);
  const href = route('/topic/') + encodeURIComponent(name);
  return `<a href="${escapeHtml(href)}" class="apx-link-topic">${escapeHtml(token)}</a>`;
}

function mentionAnchor(token) {
  const username = token.slice(1);
  if (!username) return escapeHtml(token);
  const href = route('/profile/') + encodeURIComponent(username);
  return `<a href="${escapeHtml(href)}" class="apx-link-mention">${escapeHtml(token)}</a>`;
}

export function renderRichText(text) {
  if (!text) return '';
  const re = /(https?:\/\/[^\s<>"']+)|(#[^#\s]{1,50}#)|(@[A-Za-z0-9_]{3,32})/g;
  let out = '';
  let last = 0;
  let m;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) out += escapeHtml(text.slice(last, m.index));
    if (m[1]) out += urlAnchor(m[1]);
    else if (m[2]) out += topicAnchor(m[2]);
    else if (m[3]) out += mentionAnchor(m[3]);
    last = m.index + m[0].length;
  }
  if (last < text.length) out += escapeHtml(text.slice(last));
  return out.replace(/\n/g, '<br>');
}
