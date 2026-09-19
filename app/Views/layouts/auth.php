<?php
/** Auth layout: brand split + glass form card. $content holds the page. */
use App\Core\Theme;
use App\Core\I18n;
$title = $title ?? I18n::translate('auth.login.title');
?>
<!DOCTYPE html>
<html <?= Theme::attributes() ?> lang="<?= e(I18n::getLocale()) ?>">
<head>
<?= \App\Core\View::partial('head', ['title' => $title, 'css' => ['css/pages/auth.css']]) ?>
</head>
<body class="apx-auth-wrap">
<?= \App\Core\View::partial('boot') ?>
  <div class="apx-auth">
    <aside class="apx-auth__brand">
      <div class="apx-auth__logo">
        <?= \App\Core\View::partial('logo') ?>
        <span class="apx-gradient-text" style="font-size:22px;font-weight:700;"><?= e(I18n::translate('app.name')) ?></span>
      </div>
      <div>
        <div class="apx-auth__slogan"><?= e(I18n::translate('app.slogan')) ?></div>
        <div class="apx-auth__sub"><?= e(I18n::translate('auth.brand_sub')) ?></div>
        <div class="apx-auth__features apx-mt-3">
          <div class="apx-auth__feature"><span class="dot"></span><?= e(I18n::translate('nav.friends')) ?> · <?= e(I18n::translate('nav.messages')) ?></div>
          <div class="apx-auth__feature"><span class="dot"></span><?= e(I18n::translate('nav.home')) ?> · <?= e(I18n::translate('nav.discover')) ?></div>
          <div class="apx-auth__feature"><span class="dot"></span><?= e(I18n::translate('nav.profile')) ?> · <?= e(I18n::translate('nav.settings')) ?></div>
        </div>
      </div>
      <div class="apx-text-faint" style="font-size:12px;">© <?= e(Config_get_year()) ?> APX</div>
    </aside>
    <main class="apx-auth__form">
      <div class="apx-auth__card apx-anim-pop">
        <?= $content ?? '' ?>
      </div>
    </main>
  </div>
<?= \App\Core\View::partial('scripts') ?>
</body>
</html>
<?php
function Config_get_year() { return date('Y'); }
