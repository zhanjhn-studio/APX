// APX · 导航：View Transitions 局部视图交换（仅替换 <main>，减少整页跳转白屏）
// 浏览器不支持 View Transitions 时，回退为直接替换 main 内容（仍无整页刷新）。
(function () {
  'use strict';

  const MAIN_SEL = 'main.apx-main';

  function isInternal(a) {
    if (!a || a.tagName !== 'A') return false;
    const href = a.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:') ||
        a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-apx-no-vt')) {
      return false;
    }
    try {
      const u = new URL(a.href, location.href);
      if (u.origin !== location.origin) return false;
      if (u.pathname === location.pathname && u.search === location.search) return false; // 同页不拦截
      return true;
    } catch (e) {
      return false;
    }
  }

  function extractMain(htmlText) {
    const doc = new DOMParser().parseFromString(htmlText, 'text/html');
    const m = doc.querySelector(MAIN_SEL);
    if (!m) return null;
    return { html: m.innerHTML, title: doc.title || '' };
  }

  function markActive(href) {
    try {
      const u = new URL(href, location.href);
      document.querySelectorAll('.apx-nav__item, .apx-bottom-nav a').forEach((el) => {
        try {
          const eu = new URL(el.href, location.href);
          el.classList.toggle('is-active', eu.pathname === u.pathname);
        } catch (e) { /* ignore */ }
      });
    } catch (e) { /* ignore */ }
  }

  function swap(main, part) {
    if (!main) { window.location.href = href; return; }
    main.innerHTML = part.html;
    if (part.title) document.title = part.title;
    window.scrollTo(0, 0);
    markActive(location.href);
    window.dispatchEvent(new CustomEvent('apx:navigated', { detail: { href: location.href } }));
  }

  async function navigate(href) {
    try {
      const res = await fetch(href, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('bad status');
      const part = extractMain(await res.text());
      if (!part) throw new Error('no main');
      const main = document.querySelector(MAIN_SEL);
      if (!main) { window.location.href = href; return; }
      if (document.startViewTransition) {
        document.startViewTransition(() => swap(main, part));
      } else {
        swap(main, part);
      }
      history.pushState({ apx: true }, '', href);
    } catch (e) {
      window.location.href = href; // 兜底：整页跳转
    }
  }

  document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a');
    if (!isInternal(a)) return;
    e.preventDefault();
    navigate(a.href);
  });

  // 浏览器前进 / 后退
  window.addEventListener('popstate', () => {
    const href = location.href;
    fetch(href, { credentials: 'same-origin' })
      .then((r) => r.text())
      .then((txt) => {
        const part = extractMain(txt);
        const main = document.querySelector(MAIN_SEL);
        if (part && main) {
          const apply = () => { main.innerHTML = part.html; if (part.title) document.title = part.title; markActive(href); };
          if (document.startViewTransition) document.startViewTransition(apply);
          else apply();
        } else {
          window.location.reload();
        }
      })
      .catch(() => window.location.reload());
  });
})();
