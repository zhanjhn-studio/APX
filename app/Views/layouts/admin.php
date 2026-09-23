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
    ['index',      'admin.nav.overview',   '/admin',            'admin.access',  'grid'],
    ['stats',      'admin.nav.stats',      '/admin/stats',      'admin.access',  'trending-up'],
    ['users',      'admin.nav.users',      '/admin/users',      'user.view',     'users'],
    ['posts',      'admin.nav.posts',      '/admin/posts',      'post.view',     'file'],
    ['groups',     'admin.nav.groups',     '/admin/groups',     'group.view',    'group'],
    ['blocked',    'admin.nav.blocked',    '/admin/blocked',    'user.view',     'ban'],
    ['reports',    'admin.nav.reports',    '/admin/reports',    'report.view',   'alert'],
    ['roles',      'admin.nav.roles',      '/admin/roles',      'role.manage',   'shield-check'],
    ['appearance', 'admin.nav.appearance', '/admin/appearance', 'theme.manage',  'palette'],
    ['settings',   'admin.nav.settings',   '/admin/settings',   'setting.manage','settings'],
    ['logs',       'admin.nav.logs',       '/admin/logs',       'admin.access',  'inbox'],
    ['waf',        'admin.nav.waf',        '/admin/waf',        'waf.manage',    'shield'],
    ['update',     'admin.nav.update',     '/admin/update',     'update.manage', 'download'],
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
        <?= icon('home', 20) ?>
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
          <?= icon($icon, 20) ?>
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
