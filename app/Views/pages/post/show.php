<?php
$post = $post ?? [];
?>
<div class="apx-page-head">
  <a class="apx-link" href="<?= route('/home') ?>">← <?= e(__('common.action.back')) ?></a>
</div>
<div class="apx-post-detail">
  <div id="apx-post-detail"></div>
  <?php if (!empty($post['id'])): ?>
    <div class="apx-flex apx-gap-2 apx-mt-2" style="justify-content:flex-end;">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-report data-report-type="post" data-report-id="<?= (int) $post['id'] ?>"><?= e(__('report.action')) ?></button>
    </div>
  <?php endif; ?>
</div>
<script>window.APX_POST = <?= json_encode($post, JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="<?= asset('js/modules/post-detail.js') ?>"></script>
