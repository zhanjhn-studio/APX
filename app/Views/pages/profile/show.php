<?php
use App\Core\View;

$data = $data ?? ['user' => [], 'stats' => [], 'relation' => [], 'posts' => []];
$user = $data['user'];
$stats = $data['stats'];
$relation = $data['relation'];
$achv = $data['achievement'] ?? ['level' => [], 'badges' => [], 'achievements' => [], 'unlocked' => 0, 'total' => 0];
$lvl = $achv['level'] ?? ['level' => 1, 'progress' => 0, 'current' => 0, 'needed' => 0, 'title_key' => 'level.title.newbie'];
$badges = $achv['badges'] ?? [];
$achievements = $achv['achievements'] ?? [];
$userGroups = $data['groups'] ?? [];
?>
<div class="apx-page apx-page--profile">
  <div class="apx-main">
    <section class="apx-card apx-profile-head">
      <?php if (!empty($user['cover'])): ?>
        <div class="apx-profile-head__cover" style="background-image:url('<?= e(asset('uploads/' . ltrim($user['cover'], '/'))) ?>')"></div>
      <?php endif; ?>
      <div class="apx-profile-head__body">
        <div class="apx-profile-head__avatarbox">
          <?php if (!empty($user['avatar'])): ?>
            <img class="apx-avatar apx-avatar--lg" src="<?= e(asset('uploads/' . ltrim($user['avatar'], '/'))) ?>" alt="">
          <?php else: ?>
            <span class="apx-avatar apx-avatar--lg"><?= e(mb_substr($user['nickname'] ?: $user['username'], 0, 1)) ?></span>
          <?php endif; ?>
          <span class="apx-level-ring" title="<?= e(__('level.label', [':level' => (string) $lvl['level']])) ?>">Lv.<?= (int) $lvl['level'] ?></span>
        </div>
        <div class="apx-profile-head__info">
          <div class="apx-profile-head__name">
            <?= e($user['nickname'] ?: $user['username']) ?>
            <?php if (($user['verified_type'] ?? 'none') !== 'none'): ?>
              <span class="apx-verified" title="<?= e(__('badge.verified')) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 2.4 1.8 3-.2.9 2.9 2.4 1.8-1 2.8 1 2.8-2.4 1.8-.9 2.9-3-.2L12 22l-2.4-1.8-3 .2-.9-2.9L3.3 15.7l1-2.8-1-2.8 2.4-1.8.9-2.9 3 .2Z"/><path d="m9 12 2 2 4-4" fill="none" stroke="var(--bg)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
            <?php endif; ?>
            <span class="apx-level-title"><?= e(__($lvl['title_key'])) ?></span>
          </div>
          <div class="apx-text-faint">@<?= e($user['username']) ?></div>
          <div class="apx-level-bar" title="<?= (int) $lvl['current'] ?>/<?= (int) $lvl['needed'] ?> EXP">
            <span style="width:<?= (int) $lvl['progress'] ?>%"></span>
          </div>
          <div class="apx-level-hint"><?= e(__('level.progress', [':current' => (string) $lvl['current'], ':need' => (string) $lvl['needed'], ':total' => (string) $lvl['experience']])) ?></div>

          <?php if (!empty($user['bio'])): ?>
            <p class="apx-mt-2 apx-profile-head__bio"><?= e($user['bio']) ?></p>
          <?php endif; ?>

          <?php if (!empty($badges)): ?>
            <div class="apx-badge-row apx-mt-2">
              <?php foreach ($badges as $b): ?>
                <span class="apx-medal apx-medal--<?= e($b['tone']) ?>" title="<?= e(__($b['name'])) ?> · <?= e(__($b['desc'])) ?>">
                  <span class="apx-medal__icon"><?= e($b['icon']) ?></span><?= e(__($b['name'])) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="apx-profile-head__stats apx-mt-2">
            <span><b><?= (int) $stats['posts'] ?></b> <?= e(__('profile.posts')) ?></span>
            <span><b><?= (int) $stats['following'] ?></b> <?= e(__('profile.following')) ?></span>
            <span><b><?= (int) $stats['followers'] ?></b> <?= e(__('profile.followers')) ?></span>
            <span><b><?= (int) $stats['friends'] ?></b> <?= e(__('profile.friends')) ?></span>
          </div>
        </div>
        <div class="apx-profile-head__actions">
          <?php if ($relation['is_self']): ?>
            <a class="apx-btn apx-btn--soft apx-btn--sm" href="<?= e(route('/settings')) ?>"><?= e(__('nav.settings')) ?></a>
          <?php else: ?>
            <button class="apx-btn <?= $relation['is_following'] ? 'apx-btn--soft' : 'apx-btn--primary' ?> apx-btn--sm"
                    data-follow="<?= (int) $user['id'] ?>">
              <?= e($relation['is_following'] ? __('profile.following_btn') : __('profile.follow')) ?>
            </button>
            <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/messages', ['start' => $user['username']])) ?>"><?= e(__('profile.message')) ?></a>
            <?php if (empty($relation['is_blocked'])): ?>
              <button class="apx-btn apx-btn--ghost apx-btn--sm" data-profile-block="<?= (int) $user['id'] ?>"><?= e(__('settings.block')) ?></button>
            <?php endif; ?>
            <button class="apx-btn apx-btn--ghost apx-btn--sm" data-report data-report-type="user" data-report-id="<?= (int) $user['id'] ?>"><?= e(__('report.action')) ?></button>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div id="apx-feed" class="apx-feed"></div>
  </div>

  <aside class="apx-side">
    <section class="apx-card apx-side__block">
      <div class="apx-side__title">
        <?= e(__('profile.achievements')) ?>
        <span class="apx-badge apx-badge--primary" style="margin-left:auto;"><?= (int) ($achv['unlocked'] ?? 0) ?>/<?= (int) ($achv['total'] ?? 0) ?></span>
      </div>
      <div class="apx-achv-list">
        <?php foreach ($achievements as $a): ?>
          <div class="apx-achv<?= $a['unlocked'] ? ' is-unlocked' : '' ?>" title="<?= e(__($a['desc'])) ?>">
            <span class="apx-achv__icon"><?= e($a['icon']) ?></span>
            <div class="apx-achv__body">
              <div class="apx-achv__name"><?= e(__($a['name'])) ?></div>
              <div class="apx-achv__bar"><span style="width:<?= (int) $a['percent'] ?>%"></span></div>
            </div>
            <span class="apx-achv__num"><?= (int) $a['have'] ?>/<?= (int) $a['need'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if (!empty($userGroups)): ?>
    <section class="apx-card apx-side__block">
      <div class="apx-side__title"><?= e(__('profile.groups')) ?></div>
      <?php foreach ($userGroups as $g): ?>
        <a class="apx-side-group" href="<?= e(route('/group/' . $g['slug'])) ?>">
          <span class="apx-side-group__avatar">
            <?php if (!empty($g['avatar'])): ?>
              <img src="<?= e(asset('uploads/' . ltrim($g['avatar'], '/'))) ?>" alt="" onerror="this.outerHTML='<span><?= e(mb_substr($g['name'], 0, 1)) ?></span>'">
            <?php else: ?>
              <span><?= e(mb_substr($g['name'], 0, 1)) ?></span>
            <?php endif; ?>
          </span>
          <span class="apx-side-group__name"><?= e($g['name']) ?></span>
          <span class="apx-side-group__count"><?= (int) $g['member_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="apx-card apx-side__block">
      <div class="apx-side__title"><?= e(__('profile.title')) ?></div>
      <p class="apx-text-faint apx-mt-2" style="font-size:13px;">
        <?= e(__('profile.joined', ['date' => substr($user['created_at'], 0, 10)])) ?>
      </p>
      <?php if (!empty($user['location'])): ?>
        <div class="apx-mt-2 apx-text-faint" style="font-size:13px;"><?= e($user['location']) ?></div>
      <?php endif; ?>
      <?php if (!empty($user['website'])): ?>
        <div class="apx-mt-2" style="font-size:13px;"><a class="apx-link" href="<?= e($user['website']) ?>" rel="noopener noreferrer nofollow" target="_blank" data-external="1" data-url="<?= e($user['website']) ?>"><?= e($user['website']) ?></a></div>
      <?php endif; ?>
    </section>
  </aside>
</div>

<script>
  window.APX_PROFILE = <?= json_encode(['username' => $user['username']], JSON_UNESCAPED_UNICODE) ?>;
  window.APX_POSTS = <?= json_encode($data['posts'], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= View::scripts([asset('js/modules/profile.js')]) ?>
