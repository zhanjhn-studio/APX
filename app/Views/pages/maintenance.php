<?php
/** 维护模式页面（独立整页，不依赖应用布局） */
use App\Core\Config;
use App\Core\Theme;
use App\Core\I18n;
use App\Core\View;
use App\Services\SiteSettingsService;

$siteName = SiteSettingsService::get('site_name', null) ?: Config::get('app.name', 'APX');
$slogan = SiteSettingsService::get('site_slogan', null) ?: Config::get('app.slogan', '');
?>
<!DOCTYPE html>
<html <?= Theme::attributes() ?> lang="<?= e(I18n::getLocale()) ?>">
<head>
<?= View::partial('head', ['title' => I18n::translate('maintenance.title'), 'css' => ['css/pages/auth.css']]) ?>
</head>
<body class="apx-auth-wrap">
  <div class="apx-auth__glow" aria-hidden="true"></div>
  <main class="apx-auth__form" style="max-width:520px;margin:auto;">
    <div class="apx-card apx-card--glass apx-anim-pop" style="text-align:center;padding:36px 28px;">
      <div class="apx-auth__logo" style="justify-content:center;"><?= View::partial('logo') ?></div>
      <h1 style="font-size:var(--fs-h1);margin:14px 0 8px;"><?= e($siteName) ?></h1>
      <div class="apx-badge apx-badge--warning" style="margin-bottom:14px;"><?= e(__('maintenance.badge')) ?></div>
      <p class="apx-text-muted" style="line-height:1.8;"><?= e(__('maintenance.text')) ?></p>
      <p class="apx-text-faint" style="font-size:12px;margin-top:10px;"><?= e($slogan) ?></p>
      <div class="apx-mt-3">
        <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/login')) ?>"><?= e(__('maintenance.admin_entry')) ?></a>
      </div>
    </div>
  </main>
</body>
</html>
