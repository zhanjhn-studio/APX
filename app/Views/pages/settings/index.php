<?php
/** Settings · 外观 / 安全 / 设备 / 隐私 / 免打扰 / 注销 */
use App\Core\Theme;
use App\Core\I18n;
use App\Core\View;

$user = $user ?? [];
$totp = $totp ?? ['enabled' => false, 'pending' => false, 'backup_remaining' => 0];
$devices = $devices ?? [];
$words = $words ?? [];
$muted = $muted ?? [];
$blocked = $blocked ?? [];
$dnd = $dnd ?? ['enabled' => false, 'start' => '22:00', 'end' => '08:00'];
$deletion = $deletion ?? ['deleted' => false, 'purge_at' => null, 'grace_days' => 30];
$cur = Theme::current();
$themes = ['mono','minimal','graphite','graphite_pro','dark','light','blue','purple','pink','green','gold','cyber','mint'];
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('settings.title')) ?></h1>
    <div class="apx-sub"><?= e(__('settings.subtitle')) ?></div>
  </div>
</div>

<div class="apx-settings" id="apx-settings">
  <nav class="apx-settings__nav">
    <a class="apx-settings__nav-item is-active" data-sec="appearance"><?= e(__('settings.appearance')) ?></a>
    <a class="apx-settings__nav-item" data-sec="security"><?= e(__('settings.security')) ?></a>
    <a class="apx-settings__nav-item" data-sec="devices"><?= e(__('settings.devices')) ?></a>
    <a class="apx-settings__nav-item" data-sec="privacy"><?= e(__('settings.privacy')) ?></a>
    <a class="apx-settings__nav-item" data-sec="notify"><?= e(__('settings.notify')) ?></a>
    <a class="apx-settings__nav-item" data-sec="account"><?= e(__('settings.account')) ?></a>
  </nav>

  <div class="apx-settings__body">

    <!-- 外观 -->
    <section class="apx-settings__sec is-active" data-sec="appearance">
      <div class="apx-card">
        <div class="apx-card__title"><?= e(__('settings.appearance')) ?></div>
        <div class="apx-text-muted" style="font-size:13px;margin:8px 0 12px;"><?= e(__('theme.title')) ?></div>
        <div class="apx-theme-grid" data-apx-theme-picker>
          <?php foreach ($themes as $t): ?>
            <div class="apx-theme-swatch<?= $cur['theme'] === $t ? ' is-active' : '' ?>" data-theme="<?= e($t) ?>" title="<?= e(I18n::translate('theme.name.' . $t)) ?>">
              <div class="apx-theme-swatch__bar" style="background:linear-gradient(135deg,var(--brand-1),var(--brand-3));"></div>
              <span class="apx-theme-swatch__name"><?= e(I18n::translate('theme.name.' . $t)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="apx-text-muted" style="font-size:13px;margin:18px 0 8px;"><?= e(__('theme.mode.title')) ?></div>
        <div class="apx-mode-seg" data-apx-mode-picker>
          <button data-mode="dark" class="<?= $cur['mode'] === 'dark' ? 'is-active' : '' ?>"><?= e(__('theme.mode.dark')) ?></button>
          <button data-mode="light" class="<?= $cur['mode'] === 'light' ? 'is-active' : '' ?>"><?= e(__('theme.mode.light')) ?></button>
          <button data-mode="auto" class="<?= $cur['mode'] === 'auto' ? 'is-active' : '' ?>"><?= e(__('theme.mode.auto')) ?></button>
        </div>
      </div>
    </section>

    <!-- 安全：两步验证 -->
    <section class="apx-settings__sec" data-sec="security">
      <div class="apx-card">
        <div class="apx-card__title">
          <?= e(__('settings.totp.title')) ?>
          <span class="apx-badge <?= !empty($totp['enabled']) ? 'apx-badge--success' : '' ?>" id="apx-totp-state">
            <?= e(!empty($totp['enabled']) ? __('settings.totp.on') : __('settings.totp.off')) ?>
          </span>
        </div>
        <div class="apx-card__hint"><?= e(__('settings.totp.hint')) ?></div>

        <div id="apx-totp-off" class="apx-mt-3" <?= !empty($totp['enabled']) ? 'hidden' : '' ?>>
          <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-totp-setup"><?= e(__('settings.totp.enable')) ?></button>
        </div>

        <div id="apx-totp-setup-box" class="apx-totp-setup apx-mt-3" hidden>
          <div class="apx-text-muted" style="font-size:13px;"><?= e(__('settings.totp.scan_hint')) ?></div>
          <div class="apx-totp-secret">
            <code id="apx-totp-secret"></code>
            <button class="apx-btn apx-btn--ghost apx-btn--sm" id="apx-totp-copy"><?= e(__('messages.copy')) ?></button>
          </div>
          <div class="apx-text-faint" style="font-size:12px;"><?= e(__('settings.totp.uri_hint')) ?></div>
          <code class="apx-totp-uri" id="apx-totp-uri"></code>
          <div class="apx-totp-confirm">
            <input class="apx-input" id="apx-totp-code" inputmode="numeric" maxlength="6" placeholder="<?= e(__('settings.totp.code_placeholder')) ?>">
            <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-totp-confirm"><?= e(__('common.action.confirm')) ?></button>
          </div>
        </div>

        <div id="apx-totp-on" class="apx-mt-3" <?= !empty($totp['enabled']) ? '' : 'hidden' ?>>
          <div class="apx-text-muted" style="font-size:13px;">
            <?= e(__('settings.totp.backup_remaining')) ?>: <b id="apx-totp-backup-count"><?= (int) $totp['backup_remaining'] ?></b>
          </div>
          <div class="apx-totp-actions">
            <button class="apx-btn apx-btn--soft apx-btn--sm" id="apx-totp-regen"><?= e(__('settings.totp.regen_backup')) ?></button>
            <button class="apx-btn apx-btn--danger apx-btn--sm" id="apx-totp-disable-open"><?= e(__('settings.totp.disable')) ?></button>
          </div>
          <div class="apx-totp-confirm" id="apx-totp-disable-box" hidden>
            <input class="apx-input" id="apx-totp-disable-code" maxlength="9" placeholder="<?= e(__('settings.totp.code_or_backup')) ?>">
            <button class="apx-btn apx-btn--danger apx-btn--sm" id="apx-totp-disable"><?= e(__('common.action.confirm')) ?></button>
          </div>
        </div>

        <div id="apx-totp-codes" class="apx-totp-codes" hidden>
          <div class="apx-card__title"><?= e(__('settings.totp.codes_title')) ?></div>
          <div class="apx-text-faint" style="font-size:12px;"><?= e(__('settings.totp.codes_hint')) ?></div>
          <div class="apx-totp-codes__grid" id="apx-totp-codes-grid"></div>
        </div>
      </div>
    </section>

    <!-- 设备信任 -->
    <section class="apx-settings__sec" data-sec="devices">
      <div class="apx-card">
        <div class="apx-card__title">
          <?= e(__('settings.devices')) ?>
          <button class="apx-btn apx-btn--ghost apx-btn--sm" id="apx-device-revoke-others" style="margin-left:auto;"><?= e(__('settings.devices_revoke_others')) ?></button>
        </div>
        <div class="apx-card__hint"><?= e(__('settings.devices_hint')) ?></div>
        <div class="apx-device-list" id="apx-device-list">
          <?php if (empty($devices)): ?>
            <div class="apx-text-faint" style="font-size:13px;"><?= e(__('settings.devices_empty')) ?></div>
          <?php else: foreach ($devices as $d): ?>
            <div class="apx-device" data-device="<?= e($d['id']) ?>">
              <div class="apx-device__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
              </div>
              <div class="apx-device__body">
                <div class="apx-device__name">
                  <?= e($d['device']) ?>
                  <?php if (!empty($d['is_current'])): ?><span class="apx-badge apx-badge--primary"><?= e(__('settings.device_current')) ?></span><?php endif; ?>
                  <?php if (!empty($d['trusted'])): ?><span class="apx-badge apx-badge--success"><?= e(__('settings.device_trusted')) ?></span><?php endif; ?>
                </div>
                <div class="apx-device__meta"><?= e($d['ip']) ?> · <?= e(substr((string) $d['last_active_at'], 0, 16)) ?></div>
              </div>
              <div class="apx-device__actions">
                <button class="apx-btn apx-btn--soft apx-btn--sm" data-device-trust="<?= e($d['id']) ?>" data-trusted="<?= !empty($d['trusted']) ? 1 : 0 ?>">
                  <?= e(!empty($d['trusted']) ? __('settings.device_untrust') : __('settings.device_trust')) ?>
                </button>
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-device-revoke="<?= e($d['id']) ?>"><?= e(__('settings.device_revoke')) ?></button>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </section>

    <!-- 隐私 -->
    <section class="apx-settings__sec" data-sec="privacy">
      <div class="apx-card" style="margin-bottom:16px;">
        <div class="apx-card__title"><?= e(__('settings.words')) ?></div>
        <div class="apx-card__hint"><?= e(__('settings.words_hint')) ?></div>
        <div class="apx-inline-add">
          <input class="apx-input" id="apx-word-input" maxlength="64" placeholder="<?= e(__('settings.word_placeholder')) ?>">
          <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-word-add"><?= e(__('common.add')) ?></button>
        </div>
        <div class="apx-chip-list" id="apx-word-list">
          <?php foreach ($words as $w): ?>
            <span class="apx-chip" data-word="<?= (int) $w['id'] ?>"><?= e($w['word']) ?><button data-word-remove="<?= (int) $w['id'] ?>" aria-label="remove">&times;</button></span>
          <?php endforeach; ?>
          <?php if (empty($words)): ?><span class="apx-text-faint" id="apx-word-empty" style="font-size:13px;"><?= e(__('settings.words_empty')) ?></span><?php endif; ?>
        </div>
      </div>

      <div class="apx-card" style="margin-bottom:16px;">
        <div class="apx-card__title"><?= e(__('settings.muted_users')) ?></div>
        <div class="apx-card__hint"><?= e(__('settings.muted_users_hint')) ?></div>
        <div class="apx-inline-add">
          <input class="apx-input" id="apx-mute-input" maxlength="32" placeholder="<?= e(__('settings.username_placeholder')) ?>">
          <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-mute-add"><?= e(__('common.add')) ?></button>
        </div>
        <div class="apx-user-list" id="apx-mute-list">
          <?php foreach ($muted as $m): ?>
            <div class="apx-user-row" data-user="<?= (int) $m['id'] ?>">
              <?= View::partial('avatar', ['user' => $m, 'size' => 'md']) ?>
              <div class="apx-user-row__body">
                <div class="apx-user-row__name"><?= e($m['nickname'] ?: $m['username']) ?></div>
                <div class="apx-user-row__sub">@<?= e($m['username']) ?></div>
              </div>
              <div class="apx-user-row__actions">
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-mute-remove="<?= (int) $m['id'] ?>"><?= e(__('common.action.delete')) ?></button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="apx-card">
        <div class="apx-card__title"><?= e(__('settings.blocked')) ?></div>
        <div class="apx-card__hint"><?= e(__('settings.blocked_hint')) ?></div>
        <div class="apx-inline-add">
          <input class="apx-input" id="apx-block-input" maxlength="32" placeholder="<?= e(__('settings.username_placeholder')) ?>">
          <button class="apx-btn apx-btn--danger apx-btn--sm" id="apx-block-add"><?= e(__('settings.block')) ?></button>
        </div>
        <div class="apx-user-list" id="apx-block-list">
          <?php foreach ($blocked as $b): ?>
            <div class="apx-user-row" data-user="<?= (int) $b['id'] ?>">
              <?= View::partial('avatar', ['user' => $b, 'size' => 'md']) ?>
              <div class="apx-user-row__body">
                <div class="apx-user-row__name"><?= e($b['nickname'] ?: $b['username']) ?></div>
                <div class="apx-user-row__sub">@<?= e($b['username']) ?></div>
              </div>
              <div class="apx-user-row__actions">
                <button class="apx-btn apx-btn--ghost apx-btn--sm" data-block-remove="<?= (int) $b['id'] ?>"><?= e(__('settings.unblock')) ?></button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- 免打扰 -->
    <section class="apx-settings__sec" data-sec="notify">
      <div class="apx-card">
        <div class="apx-card__title"><?= e(__('settings.dnd')) ?></div>
        <div class="apx-card__hint"><?= e(__('settings.dnd_hint')) ?></div>
        <div class="apx-dnd">
          <label class="apx-switch">
            <input type="checkbox" id="apx-dnd-enabled" <?= !empty($dnd['enabled']) ? 'checked' : '' ?>>
            <span></span>
          </label>
          <span><?= e(__('settings.dnd_enable')) ?></span>
        </div>
        <div class="apx-dnd-range">
          <label><?= e(__('settings.dnd_start')) ?><input class="apx-input" type="time" id="apx-dnd-start" value="<?= e($dnd['start']) ?>"></label>
          <label><?= e(__('settings.dnd_end')) ?><input class="apx-input" type="time" id="apx-dnd-end" value="<?= e($dnd['end']) ?>"></label>
          <button class="apx-btn apx-btn--primary apx-btn--sm" id="apx-dnd-save"><?= e(__('common.action.save')) ?></button>
        </div>
      </div>
    </section>

    <!-- 账号 -->
    <section class="apx-settings__sec" data-sec="account">
      <div class="apx-card" style="margin-bottom:16px;">
        <div class="apx-card__title"><?= e(__('settings.account')) ?></div>
        <div class="apx-text-muted" style="font-size:13px;margin:8px 0 14px;">
          @<?= e($user['username'] ?? '') ?><?php if (!empty($user['email'])): ?> · <?= e($user['email']) ?><?php endif; ?>
        </div>
        <a class="apx-btn apx-btn--ghost apx-btn--sm" href="<?= e(route('/profile')) ?>"><?= e(__('nav.profile')) ?></a>
      </div>

      <div class="apx-card apx-card--danger">
        <div class="apx-card__title"><?= e(__('settings.delete.title')) ?></div>
        <div class="apx-card__hint"><?= e(__('settings.delete.hint', [':days' => (string) ($deletion['grace_days'] ?? 30)])) ?></div>
        <?php if (!empty($deletion['deleted'])): ?>
          <div class="apx-text-muted apx-mt-2" style="font-size:13px;">
            <?= e(__('settings.delete.pending', [':date' => substr((string) $deletion['purge_at'], 0, 16)])) ?>
          </div>
          <button class="apx-btn apx-btn--soft apx-btn--sm apx-mt-3" id="apx-account-cancel-delete"><?= e(__('settings.delete.cancel')) ?></button>
        <?php else: ?>
          <div class="apx-inline-add apx-mt-3">
            <input class="apx-input" type="password" id="apx-delete-password" placeholder="<?= e(__('settings.delete.password_placeholder')) ?>" autocomplete="current-password">
            <button class="apx-btn apx-btn--danger apx-btn--sm" id="apx-account-delete"><?= e(__('settings.delete.submit')) ?></button>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </div>
</div>

<script>
  window.APX_SETTINGS = <?= json_encode(['totpEnabled' => !empty($totp['enabled'])], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= View::scripts([asset('js/modules/settings.js')]) ?>
