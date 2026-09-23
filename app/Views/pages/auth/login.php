<?php /** Auth · Login */ ?>
<div class="apx-auth__head">
  <h1><?= __('auth.login.title') ?></h1>
  <p class="apx-sub"><?= __('auth.login.subtitle') ?></p>
</div>

<div id="apx-login-error" class="apx-alert apx-alert--danger apx-hidden" style="margin-bottom:16px;"></div>

<form id="apx-login-form" action="<?= route('/login') ?>" method="post" novalidate>
  <?= csrf_field() ?>
  <div class="apx-field apx-field--wave">
    <input class="apx-input" id="apx-login-name" type="text" name="login" value="<?= e(old('login')) ?>" placeholder=" " autocomplete="username" required>
    <label class="apx-field__label" for="apx-login-name"><?= __('auth.login.username_placeholder') ?></label>
    <span class="apx-field__bar"></span>
  </div>
  <div class="apx-field apx-field--wave apx-field--pwd">
    <input class="apx-input" id="apx-pwd" type="password" name="password" placeholder=" " autocomplete="current-password" required>
    <label class="apx-field__label" for="apx-pwd"><?= __('auth.login.password_placeholder') ?></label>
    <span class="apx-field__bar"></span>
    <button type="button" class="apx-field__toggle" data-toggle-pwd="apx-pwd" aria-label="<?= e(__('auth.login.password_placeholder')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
  </div>
  <div class="apx-auth__row">
    <label class="apx-check"><input type="checkbox" name="remember" value="1"> <?= __('auth.login.remember') ?></label>
    <a class="apx-btn apx-btn--link" href="<?= route('/forgot-password') ?>"><?= __('auth.forgot.title') ?></a>
  </div>
  <button class="apx-btn apx-btn--primary apx-btn--block" type="submit"><span class="apx-btn-label"><?= __('auth.login.submit') ?></span></button>
</form>

<div class="apx-auth__alt">
  <?= __('auth.no_account') ?>
  <a href="<?= route('/register') ?>"><?= __('auth.to_register') ?></a>
</div>

<script type="module">
  import { post } from '<?= asset('js/core/http.js') ?>';
  import { toast } from '<?= asset('js/core/toast.js') ?>';
  const form = document.getElementById('apx-login-form');
  const errBox = document.getElementById('apx-login-error');
  const card = document.querySelector('.apx-auth__card');
  const base = '<?= rtrim(\App\Core\Config::get('app.base_url') ?: '', '/') ?>';
  const L = {
    totp: <?= json_encode(__('auth.login.totp_required'), JSON_UNESCAPED_UNICODE) ?>,
    confirm: <?= json_encode(__('common.action.confirm'), JSON_UNESCAPED_UNICODE) ?>
  };
  // 服务端 message 为 i18n key，需用注入的语言包翻译，避免直接显示原始 key
  const tr = (k) => (window.APX_I18N && Object.prototype.hasOwnProperty.call(window.APX_I18N, k)) ? window.APX_I18N[k] : k;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type=submit]');
    btn.classList.add('is-loading'); btn.disabled = true;
    errBox.classList.add('apx-hidden');
    if (window.apx && window.apx.loader) window.apx.loader.show(card);
    try {
      const json = await post(form.getAttribute('action'), new FormData(form));
      if (json.data && json.data.require_totp) {
        if (window.apx && window.apx.loader) window.apx.loader.hide(card);
        renderTotp(); return;
      }
      toast.success(json.message);
      location.href = json.data.redirect || '<?= route('/home') ?>';
    } catch (err) {
      errBox.textContent = (err.errors && Object.values(err.errors)[0]) || tr(err.message);
      errBox.classList.remove('apx-hidden');
    } finally {
      btn.classList.remove('is-loading'); btn.disabled = false;
      if (window.apx && window.apx.loader) window.apx.loader.hide(card);
    }
  });

  function renderTotp() {
    form.setAttribute('action', '<?= route('/login/2fa') ?>');
    form.innerHTML = ''
      + '<div class="apx-field apx-field--wave">'
      +   '<input class="apx-input" id="apx-totp-code" type="text" name="code" placeholder=" " inputmode="numeric" autocomplete="one-time-code" required>'
      +   '<label class="apx-field__label" for="apx-totp-code">' + L.totp + '</label>'
      +   '<span class="apx-field__bar"></span>'
      + '</div>'
      + '<button class="apx-btn apx-btn--primary apx-btn--block" type="submit"><span class="apx-btn-label">' + L.confirm + '</span></button>';
    // 关键：innerHTML 会清空 CSRF 隐藏字段，必须补回，否则二次提交被 CSRF 拦截
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'csrf_token';
      inp.value = csrf.getAttribute('content');
      form.appendChild(inp);
    }
    form.querySelector('input').focus();
  }

  document.querySelectorAll('[data-toggle-pwd]').forEach((b) => b.addEventListener('click', () => {
    const i = document.getElementById(b.getAttribute('data-toggle-pwd'));
    i.type = i.type === 'password' ? 'text' : 'password';
  }));
</script>
