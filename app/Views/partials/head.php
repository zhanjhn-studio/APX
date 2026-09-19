<?php
/** Partial: <head> contents. $title, $css (array), $realtime (bool) */
use App\Core\Config;
use App\Core\I18n;
use App\Core\Csrf;
use App\Services\AuthService;
use App\Services\SiteSettingsService;

$apxSiteName = SiteSettingsService::get('site_name', null) ?: Config::get('app.name', 'APX');
$apxSlogan = SiteSettingsService::get('site_slogan', null) ?: Config::get('app.slogan', '');
$title = $title ?? $apxSiteName;
$cssList = $css ?? [];
$realtime = $realtime ?? false;

$apxUser = AuthService::user() ?: [];
$apxAvatar = !empty($apxUser['avatar']) ? asset('uploads/' . ltrim($apxUser['avatar'], '/')) : '';
$apxBase = Config::get('app.base_url') ?: '';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark light">
<title><?= e($title) ?> · <?= e($apxSiteName) ?></title>
<meta name="description" content="<?= e($apxSlogan) ?>">
<?= csrf_meta() ?>
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
<link rel="stylesheet" href="<?= asset('css/components.css') ?>">
<link rel="stylesheet" href="<?= asset('css/animations.css') ?>">
<link rel="stylesheet" href="<?= asset('css/loading.css') ?>">
<link rel="stylesheet" href="<?= asset('css/components-ref.css') ?>">
<link rel="stylesheet" href="<?= asset('css/themes.css') ?>">
<?php foreach ($cssList as $c): ?><link rel="stylesheet" href="<?= asset($c) ?>">
<?php endforeach; ?>
<script>
  window.APX_CONFIG = {
    baseUrl: <?= json_encode($apxBase) ?>,
    pretty: <?= Config::get('app.pretty_urls', false) ? 'true' : 'false' ?>,
    realtime: <?= $realtime ? 'true' : 'false' ?>,
    locale: <?= json_encode(I18n::getLocale()) ?>
  };
  window.APX_I18N = <?= json_encode(I18n::export(), JSON_UNESCAPED_UNICODE) ?>;
  window.APX = {
    base: <?= json_encode($apxBase) ?>,
    csrf: <?= json_encode(Csrf::token()) ?>,
    user: {
      id: <?= (int) ($apxUser['id'] ?? 0) ?>,
      username: <?= json_encode($apxUser['username'] ?? '') ?>,
      nickname: <?= json_encode($apxUser['nickname'] ?? '') ?>,
      avatar: <?= json_encode($apxAvatar) ?>
    }
  };
</script>
<script type="module" src="<?= asset('js/app.js') ?>"></script>
