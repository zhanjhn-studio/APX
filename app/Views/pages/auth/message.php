<?php /** Auth · Result message (verify / reset). $type, $title, $text */ ?>
<?php
$type = $type ?? 'success';
$isError = $type === 'error';
?>
<div class="apx-text-center apx-anim-pop">
  <div style="width:72px;height:72px;border-radius:50%;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;
    background:<?= $isError ? 'color-mix(in srgb, var(--danger) 18%, transparent)' : 'var(--primary-soft)' ?>;">
    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="<?= $isError ? 'var(--danger)' : 'var(--primary)' ?>" stroke-width="2.2">
      <?= $isError ? '<path d="M18 6 6 18M6 6l12 12"/>' : '<path d="M20 6 9 17l-5-5"/>' ?>
    </svg>
  </div>
  <h1 style="font-size:var(--fs-h1);"><?= e($title ?? '') ?></h1>
  <p class="apx-mt-2 apx-text-muted"><?= e($text ?? '') ?></p>
  <a class="apx-btn apx-btn--primary apx-mt-3" href="<?= route('/login') ?>"><?= __('auth.to_login') ?></a>
</div>
