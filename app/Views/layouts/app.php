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
      <?= icon('search', 18) ?>
      <input type="search" name="q" placeholder="<?= e(I18n::translate('search.placeholder')) ?>" aria-label="<?= e(I18n::translate('search.title')) ?>">
    </form>
    <div class="apx-topbar__actions">
      <button class="apx-icon-btn" id="apx-theme-toggle" title="<?= e(I18n::translate('theme.name.graphite')) ?>" onclick="apxThemeMenu(event)">
        <?= icon('moon', 20) ?>
      </button>
      <a class="apx-icon-btn" href="<?= route('/notifications') ?>" title="<?= e(I18n::translate('nav.notifications')) ?>">
        <?= icon('bell', 20) ?>
        <span class="apx-badge-dot" id="apx-noti-count" style="display:none">0</span>
      </a>
      <a class="apx-icon-btn" href="<?= route('/messages') ?>" title="<?= e(I18n::translate('nav.messages')) ?>">
        <?= icon('message', 20) ?>
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
      <a class="apx-nav__item" href="<?= route('/home') ?>"><?= icon('home', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.home')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/discover') ?>"><?= icon('compass', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.discover')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/messages') ?>"><?= icon('message', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.messages')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/friends') ?>"><?= icon('users', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.friends')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/groups') ?>"><?= icon('group', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.groups')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/notifications') ?>"><?= icon('bell', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.notifications')) ?></span></a>
      <div class="apx-nav__section"><?= e(I18n::translate('nav.profile')) ?></div>
      <a class="apx-nav__item" href="<?= route('/profile') ?>"><?= icon('user', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.profile')) ?></span></a>
      <a class="apx-nav__item" href="<?= route('/settings') ?>"><?= icon('settings', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.settings')) ?></span></a>
      <?php if (!empty($user['role_level']) && $user['role_level'] >= 100): ?>
      <div class="apx-nav__section"><?= e(I18n::translate('nav.admin')) ?></div>
      <a class="apx-nav__item" href="<?= route('/admin') ?>"><?= icon('shield', 20) ?><span class="apx-nav__label"><?= e(I18n::translate('nav.admin')) ?></span></a>
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
    <a href="<?= route('/home') ?>"><?= icon('home', 22) ?><span><?= e(I18n::translate('nav.home')) ?></span></a>
    <a href="<?= route('/discover') ?>"><?= icon('compass', 22) ?><span><?= e(I18n::translate('nav.discover')) ?></span></a>
    <a href="<?= route('/messages') ?>"><?= icon('message', 22) ?><span><?= e(I18n::translate('nav.messages')) ?></span><span class="apx-badge-dot" id="apx-msg-count-m" style="display:none">0</span></a>
    <a href="<?= route('/friends') ?>"><?= icon('users', 22) ?><span><?= e(I18n::translate('nav.friends')) ?></span></a>
    <a href="<?= route('/profile') ?>"><?= icon('user', 22) ?><span><?= e(I18n::translate('nav.profile')) ?></span></a>
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
<?= \App\Core\View::scripts([asset('js/nav.js')]) ?>

  <!-- 悬浮发布按钮（Uiverse FAB · mono 自适应） -->
  <div class="apx-fab" id="apx-fab" aria-label="<?= e(__('publish.title')) ?>">
    <input type="checkbox" id="apx-fab-toggle" class="apx-fab__trigger">
    <div class="apx-fab__subs">
      <a href="<?= route('/publish', ['type' => 'text']) ?>" class="apx-fab__sub" title="<?= e(__('publish.type_text')) ?>"><?= icon('edit', 20) ?></a>
      <a href="<?= route('/publish', ['type' => 'image']) ?>" class="apx-fab__sub" title="<?= e(__('publish.type_image')) ?>"><?= icon('image', 20) ?></a>
      <a href="<?= route('/publish', ['type' => 'video']) ?>" class="apx-fab__sub" title="<?= e(__('publish.type_video')) ?>"><?= icon('video', 20) ?></a>
      <a href="<?= route('/publish') ?>" class="apx-fab__sub" title="<?= e(__('publish.title')) ?>"><?= icon('comment', 20) ?></a>
    </div>
    <label for="apx-fab-toggle" class="apx-fab__main" title="<?= e(__('publish.title')) ?>">
      <?= icon('plus', 24) ?>
    </label>
  </div>
  <script>
    // 发布页本身不再显示悬浮按钮
    if (/(^|\/)publish(\.php)?($|\?)/.test(location.pathname + location.search)) {
      var f = document.getElementById('apx-fab'); if (f) f.style.display = 'none';
    }
  </script>

  <!-- Cookie 同意横幅（Uiverse 00Kubi） -->
  <?= \App\Core\View::partial('cookie') ?>

  <!-- 全局加载遮罩（Uiverse satyamchaudharydev 方块 spinner） -->
  <div class="apx-loading" id="apxLoading"><div class="apx-spinner-blocks"></div></div>
  <script>
    window.APX = window.APX || {};
    window.APX.showLoading = function () { var e = document.getElementById('apxLoading'); if (e) e.classList.add('is-on'); };
    window.APX.hideLoading = function () { var e = document.getElementById('apxLoading'); if (e) e.classList.remove('is-on'); };
  </script>
</body>
</html>
