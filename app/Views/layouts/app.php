<?php
/** App shell: glass topbar, sidebar nav, main content, mobile bottom nav. */
use App\Core\Config;
use App\Core\Theme;
use App\Core\I18n;
use App\Services\AuthService;
$title = $title ?? I18n::translate('nav.home');
$user = AuthService::user() ?: [];
$initial = !empty($user['nickname']) ? mb_substr($user['nickname'], 0, 1) : (!empty($user['username']) ? mb_substr($user['username'], 0, 1) : '?');
?>
<!DOCTYPE html>
<html <?= Theme::attributes() ?> lang="<?= e(I18n::getLocale()) ?>">
<head>
<?= \App\Core\View::partial('head', ['title' => $title, 'css' => $css ?? [], 'realtime' => true]) ?>
</head>
<body class="apx-app-wrap">
<?= \App\Core\View::partial('boot') ?>
  <header class="apx-topbar">
    <a class="apx-topbar__logo" href="<?= route('/home') ?>">
      <?= \App\Core\View::partial('logo') ?>
      <span><?= e(I18n::translate('app.name')) ?></span>
    </a>
    <form class="apx-search" action="<?= route('/search') ?>" method="get" role="search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="search" name="q" placeholder="<?= e(I18n::translate('search.placeholder')) ?>" aria-label="<?= e(I18n::translate('search.title')) ?>">
    </form>
    <div class="apx-topbar__actions">
      <button class="apx-icon-btn" id="apx-theme-toggle" title="<?= e(I18n::translate('theme.name.graphite')) ?>" onclick="apxThemeMenu(event)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      </button>
      <a class="apx-icon-btn" href="<?= route('/notifications') ?>" title="<?= e(I18n::translate('nav.notifications')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <span class="apx-badge-dot" id="apx-noti-count" style="display:none">0</span>
      </a>
      <a class="apx-icon-btn" href="<?= route('/messages') ?>" title="<?= e(I18n::translate('nav.messages')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>
        <span class="apx-badge-dot" id="apx-msg-count" style="display:none">0</span>
      </a>
      <button class="apx-avatar-btn" data-apx-menu-trigger="apx-user-menu">
        <span class="apx-avatar"><?= e($initial) ?></span>
      </button>
      <div class="apx-menu" id="apx-user-menu" style="right:14px;top:56px;">
        <a class="apx-menu__item" href="<?= route('/profile') ?>"><?= e(I18n::translate('nav.profile')) ?></a>
        <a class="apx-menu__item" href="<?= route('/favorites') ?>"><?= e(I18n::translate('favorite.title')) ?></a>
        <a class="apx-menu__item" href="<?= route('/settings') ?>"><?= e(I18n::translate('nav.settings')) ?></a>
        <?php if (!empty($user['role_level']) && $user['role_level'] >= 100): ?>
        <a class="apx-menu__item" href="<?= route('/admin') ?>"><?= e(I18n::translate('nav.admin')) ?></a>
        <?php endif; ?>
        <div class="apx-menu__sep"></div>
        <a class="apx-menu__item is-danger" href="#" data-apx-logout><?= e(I18n::translate('auth.logout')) ?></a>
      </div>
    </div>
  </header>

  <aside class="apx-sidebar">
    <nav class="apx-nav">
      <a class="apx-nav__item" href="<?= route('/home') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.home')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/discover') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m15 9-2 6-4 2 2-6Z"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.discover')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/messages') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.messages')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/friends') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M17 20a6 6 0 0 0-3-5.2"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.friends')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/groups') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="9" r="2.6"/><circle cx="16" cy="9" r="2.6"/><path d="M3 19a5 5 0 0 1 10 0"/><path d="M11 19a5 5 0 0 1 10 0"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.groups')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/notifications') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.notifications')) ?></span></a>
      <div class="apx-nav__section"><?= e(I18n::translate('nav.profile')) ?></div>
      <a class="apx-nav__item" href="<?= route('/profile') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.profile')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/settings') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6.6 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 13.4H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 6.6l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 4.6V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8Z"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.settings')) ?></span></a>
      <?php if (!empty($user['role_level']) && $user['role_level'] >= 100): ?>
      <div class="apx-nav__section"><?= e(I18n::translate('nav.admin')) ?></div>
      <a class="apx-nav__item" href="<?= route('/admin') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6Z"/><path d="m9 12 2 2 4-4"/></svg><span class="apx-nav__label"><?= e(I18n::translate('nav.admin')) ?></span></a>
      <?php endif; ?>
    </nav>
    <div class="apx-sidebar__foot apx-text-faint" style="font-size:12px;">APX · v<?= e(Config::get('app.version', '1.0.0')) ?></div>
  </aside>

  <main class="apx-main">
    <?php
    $apxAnnouncement = trim((string) (\App\Services\SiteSettingsService::get('announcement', '') ?? ''));
    if ($apxAnnouncement !== ''):
        $apxAnnounceKey = substr(md5($apxAnnouncement), 0, 12);
    ?>
    <div class="apx-announce" id="apx-announce" data-key="<?= e($apxAnnounceKey) ?>" hidden>
      <span class="apx-announce__tag"><?= e(I18n::translate('site.announcement_title')) ?></span>
      <span class="apx-announce__text"><?= nl2br(e($apxAnnouncement)) ?></span>
      <button class="apx-icon-btn apx-announce__close" id="apx-announce-close" title="<?= e(I18n::translate('site.announcement_close')) ?>">✕</button>
    </div>
    <script>
      (function () {
        var el = document.getElementById('apx-announce');
        if (!el) return;
        try {
          if (localStorage.getItem('apx_announce_hide') === el.dataset.key) return;
        } catch (e) { /* 隐私模式忽略 */ }
        el.hidden = false;
        document.getElementById('apx-announce-close').addEventListener('click', function () {
          el.remove();
          try { localStorage.setItem('apx_announce_hide', el.dataset.key); } catch (e) { /* ignore */ }
        });
      })();
    </script>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>

  <nav class="apx-bottom-nav">
    <a href="<?= route('/home') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg><span><?= e(I18n::translate('nav.home')) ?></span></a>
    <a href="<?= route('/discover') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m15 9-2 6-4 2 2-6Z"/></svg><span><?= e(I18n::translate('nav.discover')) ?></span></a>
    <a href="<?= route('/messages') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg><span><?= e(I18n::translate('nav.messages')) ?></span><span class="apx-badge-dot" id="apx-msg-count-m" style="display:none">0</span></a>
    <a href="<?= route('/friends') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/></svg><span><?= e(I18n::translate('nav.friends')) ?></span></a>
    <a href="<?= route('/profile') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg><span><?= e(I18n::translate('nav.profile')) ?></span></a>
  </nav>

  <!-- Theme / appearance popover -->
  <div class="apx-menu" id="apx-theme-menu" style="right:14px;top:56px;width:300px;padding:16px;">
    <div class="apx-card__title" style="margin-bottom:12px;"><?= e(I18n::translate('theme.title')) ?> · <?= e(I18n::translate('theme.name.' . Theme::current()['theme'])) ?></div>
    <div class="apx-text-muted" style="font-size:12px;margin-bottom:8px;"><?= e(I18n::translate('app.slogan')) ?></div>
    <div class="apx-theme-grid" data-apx-theme-picker>
      <?php foreach (['mono','minimal','graphite','graphite_pro','dark','light','blue','purple','pink','green','gold','cyber','mint'] as $t): ?>
      <div class="apx-theme-swatch<?= (Theme::current()['theme'] === $t) ? ' is-active' : '' ?>" data-theme="<?= e($t) ?>" title="<?= e(I18n::translate('theme.name.'.$t)) ?>">
        <div class="apx-theme-swatch__bar" style="background:linear-gradient(135deg,var(--brand-1),var(--brand-3));"></div>
        <span class="apx-theme-swatch__name"><?= e(I18n::translate('theme.name.'.$t)) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="apx-text-muted apx-mt-3" style="font-size:12px;margin-bottom:8px;"><?= e(I18n::translate('theme.mode.title')) ?></div>
    <div class="apx-mode-seg" data-apx-mode-picker>
      <button data-mode="dark" class="<?= Theme::current()['mode'] === 'dark' ? 'is-active' : '' ?>"><?= e(I18n::translate('theme.mode.dark')) ?></button>
      <button data-mode="light" class="<?= Theme::current()['mode'] === 'light' ? 'is-active' : '' ?>"><?= e(I18n::translate('theme.mode.light')) ?></button>
      <button data-mode="auto" class="<?= Theme::current()['mode'] === 'auto' ? 'is-active' : '' ?>"><?= e(I18n::translate('theme.mode.auto')) ?></button>
    </div>
  </div>

<?= \App\Core\View::partial('scripts') ?>
<script>
  function apxThemeMenu(e){ e.stopPropagation(); var m=document.getElementById('apx-theme-menu'); var open=m.classList.contains('is-open');
    document.querySelectorAll('.apx-menu.is-open').forEach(function(x){x.classList.remove('is-open');}); if(!open) m.classList.add('is-open'); }
</script>
<?= \App\Core\View::scripts([asset('js/modules/report.js')]) ?>
</body>
</html>
