<?php
use App\Core\View;

$allFolders = [];
$allFolders[] = ['id' => 0, 'name' => __('favorite.default'), 'count' => $defaultCount];
foreach ($folders as $f) {
    $allFolders[] = ['id' => (int) $f['id'], 'name' => $f['name'], 'count' => (int) $f['count']];
}
?>
<div class="apx-page apx-page--favorites">
  <div class="apx-main">
    <div class="apx-page__head">
      <h1 class="apx-page__title"><?= e(__('favorite.title')) ?></h1>
    </div>
    <div id="apx-feed" class="apx-feed"></div>
  </div>

  <aside class="apx-side">
    <section class="apx-card apx-side__block">
      <div class="apx-side__title apx-flex apx-justify-between">
        <span><?= e(__('favorite.folders')) ?></span>
        <button id="apx-folder-add" class="apx-btn apx-btn--ghost apx-btn--sm">+ <?= e(__('favorite.new_folder')) ?></button>
      </div>
      <a class="apx-folder-row <?= ($activeFolder === null) ? 'is-active' : '' ?>" href="<?= e(route('/favorites')) ?>">
        <span><?= e(__('favorite.all')) ?></span>
        <span class="apx-text-faint"><?= (int) $defaultCount ?></span>
      </a>
      <?php foreach ($folders as $f): ?>
        <div class="apx-folder-row <?= ((int) $activeFolder === (int) $f['id']) ? 'is-active' : '' ?>">
          <a class="apx-folder-row__main" href="<?= e(route('/favorites?folder=' . $f['id'])) ?>">
            <span><?= e($f['name']) ?></span>
            <span class="apx-text-faint"><?= (int) $f['count'] ?></span>
          </a>
          <div class="apx-folder-row__ops">
            <button class="apx-icon-btn" data-folder-rename="<?= (int) $f['id'] ?>" data-name="<?= e($f['name']) ?>" title="<?= e(__('favorite.rename')) ?>"><?= icon('edit', 18) ?></button>
            <button class="apx-icon-btn" data-folder-delete="<?= (int) $f['id'] ?>" title="<?= e(__('favorite.delete_folder')) ?>"><?= icon('trash', 18) ?></button>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </aside>
</div>

<script>
  window.APX_FAVS = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;
  window.APX_FOLDERS = <?= json_encode($allFolders, JSON_UNESCAPED_UNICODE) ?>;
  window.APX_ACTIVE_FOLDER = <?= ($activeFolder === null ? 'null' : (int) $activeFolder) ?>;
</script>
<?= View::scripts([asset('js/modules/favorites.js')]) ?>
