<?php
/** Auth layout: aurora background + brand hero + glass form card. $content holds the page. */
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
  <div class="apx-auth__bg" aria-hidden="true"></div>

  <div class="apx-auth">
    <aside class="apx-auth__hero">
      <div class="apx-auth__logo">
        <?= \App\Core\View::partial('logo') ?>
        <span class="apx-gradient-text apx-auth__brand"><?= e(I18n::translate('app.name')) ?></span>
      </div>
      <h2 class="apx-auth__hero-title"><?= e(I18n::translate('app.slogan')) ?></h2>
      <p class="apx-auth__hero-sub"><?= e(I18n::translate('auth.brand_sub')) ?></p>
      <ul class="apx-auth__features">
        <li class="apx-auth__feature"><span class="dot"></span><?= e(I18n::translate('nav.friends')) ?> · <?= e(I18n::translate('nav.messages')) ?></li>
        <li class="apx-auth__feature"><span class="dot"></span><?= e(I18n::translate('nav.home')) ?> · <?= e(I18n::translate('nav.discover')) ?></li>
        <li class="apx-auth__feature"><span class="dot"></span><?= e(I18n::translate('nav.profile')) ?> · <?= e(I18n::translate('nav.settings')) ?></li>
      </ul>
      <div class="apx-auth__hero-foot">© <?= e(Config_get_year()) ?> APX</div>
    </aside>

    <main class="apx-auth__form">
      <div class="apx-auth__card apx-glass apx-anim-pop">
        <?php
        $apxCustomBefore = \App\Services\CustomHtmlService::render('before');
        if ($apxCustomBefore !== '') {
            echo '<div class="apx-custom-html">' . $apxCustomBefore . '</div>';
        }
        ?>
        <?= $content ?? '' ?>
        <?php
        $apxCustomAfter = \App\Services\CustomHtmlService::render('after');
        if ($apxCustomAfter !== '') {
            echo '<div class="apx-custom-html">' . $apxCustomAfter . '</div>';
        }
        ?>
      </div>
    </main>
  </div>
<?= \App\Core\View::partial('scripts') ?>
</body>
</html>
<?php
function Config_get_year() { return date('Y'); }
