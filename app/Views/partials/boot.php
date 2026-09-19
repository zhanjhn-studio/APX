<?php
/** 首屏 boot 遮罩：显示立方体 spinner，直到 app.js 就绪后隐藏。 */
use App\Core\I18n;
?>
<div id="apx-boot" class="apx-boot" aria-hidden="true">
  <div class="apx-boot__inner">
    <div class="apx-spinner-3d"><div></div><div></div><div></div><div></div><div></div><div></div></div>
    <div class="apx-boot__brand"><?= e(I18n::translate('app.name')) ?></div>
  </div>
</div>
