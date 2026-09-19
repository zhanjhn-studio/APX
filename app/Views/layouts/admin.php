<?php
/** Admin layout: topbar + admin sidebar (permission-aware) + content. */
use App\Core\Theme;
use App\Core\I18n;
use App\Services\AuthService;
use App\Services\PermissionService;
use App\Services\ReportService;

$title = $title ?? I18n::translate('nav.admin');
$user = AuthService::user() ?: [];
$initial = !empty($user['nickname']) ? mb_substr($user['nickname'], 0, 1) : 'A';
$seg = $seg ?? '';

$pendingReports = 0;
try {
    $pendingReports = (int) (ReportService::counts()['pending'] ?? 0);
} catch (\Throwable $e) {
    $pendingReports = 0;
}

$nav = [
    ['index',      'admin.nav.overview',   '/admin',            'admin.access',  'M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z'],
    ['stats',      'admin.nav.stats',      '/admin/stats',      'admin.access',  'M4 20V10M10 20V4M16 20v-7M22 20H2'],
    ['users',      'admin.nav.users',      '/admin/users',      'user.view',     'M12 8a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7ZM5 20a7 7 0 0 1 14 0'],
    ['posts',      'admin.nav.posts',      '/admin/posts',      'post.view',     'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5'],
    ['groups',     'admin.nav.groups',     '/admin/groups',     'group.view',    'M8 9a2.6 2.6 0 1 0 0-5.2A2.6 2.6 0 0 0 8 9ZM16 9a2.6 2.6 0 1 0 0-5.2A2.6 2.6 0 0 0 16 9ZM3 19a5 5 0 0 1 10 0M11 19a5 5 0 0 1 10 0'],
    ['blocked',    'admin.nav.blocked',    '/admin/blocked',    'user.view',     'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM5.6 5.6l12.8 12.8'],
    ['reports',    'admin.nav.reports',    '/admin/reports',    'report.view',   'M12 3 2 20h20L12 3ZM12 9v5M12 17h.01'],
    ['roles',      'admin.nav.roles',      '/admin/roles',      'role.manage',   'M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6ZM9 12l2 2 4-4'],
    ['appearance', 'admin.nav.appearance', '/admin/appearance', 'theme.manage',  'M12 3a9 9 0 0 0 0 18c1.7 0 2-1.3 1-2.2-1-1 0-2.3 1.4-2.3H17a4 4 0 0 0 4-4A9 9 0 0 0 12 3Z'],
    ['settings',   'admin.nav.settings',   '/admin/settings',   'setting.manage','M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.6 1.6 0 0 0 .3 1.8 2 2 0 1 1-2.8 2.8 1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0 1.6 1.6 0 0 0-2.7-1.1 2 2 0 1 1-2.8-2.8A1.6 1.6 0 0 0 3 13.4H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 6.6a2 2 0 1 1 2.8-2.8A1.6 1.6 0 0 0 10 4.6V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1 2 2 0 1 1 2.8 2.8 1.6 1.6 0 0 0-.3 1.8'],
    ['logs',       'admin.nav.logs',       '/admin/logs',       'admin.access',  'M6 3h9l4 4v14H6zM15 3v4h4M9 12h7M9 16h5'],
    ['waf',        'admin.nav.waf',        '/admin/waf',        'waf.manage',    'M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6Z'],
    ['update',     'admin.nav.update',     '/admin/update',     'update.manage', 'M12 3v12M7 10l5 5 5-5M5 21h14'],
];
?>
<!DOCTYPE html>
<html <?= Theme::attributes() ?> lang="<?= e(I18n::getLocale()) ?>">
<head>
<?= \App\Core\View::partial('head', ['title' => $title, 'css' => ['css/pages/admin.css']]) ?>
</head>
<body class="apx-admin-wrap">
  <header class="apx-topbar">
    <a class="apx-topbar__logo" href="<?= route('/admin') ?>">
      <?= \App\Core\View::partial('logo') ?>
      <span><?= e(I18n::translate('nav.admin')) ?></span>
    </a>
    <div class="apx-topbar__actions">
      <a class="apx-icon-btn" href="<?= route('/home') ?>" title="<?= e(I18n::translate('nav.home')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>
      </a>
      <button class="apx-avatar-btn" data-apx-menu-trigger="apx-admin-menu">
        <span class="apx-avatar"><?= e($initial) ?></span>
      </button>
      <div class="apx-menu" id="apx-admin-menu" style="right:14px;top:56px;">
        <a class="apx-menu__item" href="<?= route('/profile') ?>"><?= e(I18n::translate('nav.profile')) ?></a>
        <a class="apx-menu__item" href="<?= route('/settings') ?>"><?= e(I18n::translate('nav.settings')) ?></a>
        <div class="apx-menu__sep"></div>
        <a class="apx-menu__item is-danger" href="#" data-apx-logout><?= e(I18n::translate('auth.logout')) ?></a>
      </div>
    </div>
  </header>

  <aside class="apx-admin__side">
    <nav class="apx-admin-nav">
      <?php foreach ($nav as [$key, $labelKey, $path, $perm, $icon]): ?>
        <?php if (!PermissionService::can($perm)) { continue; } ?>
        <a class="<?= $seg === $key ? 'is-active' : '' ?>" href="<?= route($path) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="<?= e($icon) ?>"/></svg>
          <span><?= e(I18n::translate($labelKey)) ?></span>
          <?php if ($key === 'reports' && $pendingReports > 0): ?>
            <span class="apx-admin-nav__badge"><?= $pendingReports > 99 ? '99+' : $pendingReports ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <main class="apx-admin__main">
    <?= $content ?? '' ?>
  </main>

<?= \App\Core\View::partial('scripts') ?>
<?= \App\Core\View::scripts([asset('js/modules/admin.js')]) ?>
</body>
</html>
