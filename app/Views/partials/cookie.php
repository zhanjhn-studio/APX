<?php
/** Cookie 同意横幅（Uiverse 00Kubi · mono 自适应）。存储于 localStorage，不依赖后端。 */
use App\Core\I18n;
?>
<div class="apx-cookie" id="apx-cookie" hidden>
  <svg class="apx-cookie__svg" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4-4 4 4 0 0 1-4-4 2 2 0 0 0-2-2Zm-3 13a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm-3-5a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm8-1a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Z"/></svg>
  <div class="apx-cookie__heading"><?= e(I18n::translate('cookie.title')) ?></div>
  <div class="apx-cookie__desc">
    <?= e(I18n::translate('cookie.desc')) ?>
    <a href="<?= route('/privacy') ?>" onclick="return false;"><?= e(I18n::translate('cookie.policy')) ?></a>
  </div>
  <div class="apx-cookie__actions">
    <button class="apx-cookie__decline" id="apx-cookie-decline" type="button"><?= e(I18n::translate('cookie.decline')) ?></button>
    <button class="apx-cookie__accept" id="apx-cookie-accept" type="button"><?= e(I18n::translate('cookie.accept')) ?></button>
  </div>
</div>
<script>
  (function () {
    var KEY = 'apx_cookie_consent';
    var box = document.getElementById('apx-cookie');
    if (!box) return;
    var stored;
    try { stored = localStorage.getItem(KEY); } catch (e) { stored = '1'; }
    if (stored) return;
    box.hidden = false;
    function save(v) {
      try { localStorage.setItem(KEY, v); } catch (e) {}
      box.hidden = true;
    }
    document.getElementById('apx-cookie-accept').addEventListener('click', function () { save('1'); });
    document.getElementById('apx-cookie-decline').addEventListener('click', function () { save('0'); });
  })();
</script>
