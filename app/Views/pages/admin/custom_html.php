<?php
/** Admin · 自定义前端 HTML（v3）：提示词工坊 + 全站前台注入 */
$settings = $settings ?? [];
$manifest = $manifest ?? \App\Services\CustomHtmlService::INTERFACE_MANIFEST;
$prompt = $prompt ?? '';
$val = fn(string $k, string $d = ''): string => (string) ($settings[$k] ?? $d);
$ifaceVal = $val('custom_html_interface', $manifest);
$phText = implode("\n", array_map(static fn(string $p) => ' - ' . $p, \App\Services\CustomHtmlService::placeholders()));
$position = $val('custom_html_position', \App\Services\CustomHtmlService::DEFAULT_POSITION);
?>
<div class="apx-page-head">
  <div>
    <h1><?= e(__('admin.nav.custom_html')) ?></h1>
    <div class="apx-sub"><?= e(__('admin.custom_html.subtitle')) ?></div>
  </div>
</div>

<form id="apx-custom-html" action="<?= route('/admin/custom-html/save') ?>" method="post">
  <?= csrf_field() ?>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.custom_html.group.basic')) ?></div>
    <div class="apx-form-grid">
      <label class="apx-switch-row">
        <span><?= e(__('admin.custom_html.enabled')) ?></span>
        <span class="apx-switch">
          <input type="checkbox" name="custom_html_enabled" value="1" <?= $val('custom_html_enabled', '0') === '1' ? 'checked' : '' ?>>
          <span></span>
        </span>
      </label>
      <label class="apx-field">
        <span class="apx-field__label"><?= e(__('admin.custom_html.position')) ?></span>
        <select class="apx-input" name="custom_html_position">
          <option value="before_content" <?= $position === 'before_content' ? 'selected' : '' ?>><?= e(__('admin.custom_html.position.before')) ?></option>
          <option value="after_content" <?= $position === 'after_content' ? 'selected' : '' ?>><?= e(__('admin.custom_html.position.after')) ?></option>
          <option value="both" <?= $position === 'both' ? 'selected' : '' ?>><?= e(__('admin.custom_html.position.both')) ?></option>
        </select>
      </label>
    </div>
    <p class="apx-card__hint"><?= e(__('admin.custom_html.position_hint')) ?></p>
  </div>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.custom_html.group.prompt')) ?></div>
    <div class="apx-form-grid">
      <label class="apx-field apx-field--wide">
        <span class="apx-field__label"><?= e(__('admin.custom_html.instruction')) ?></span>
        <textarea class="apx-textarea" name="custom_html_instruction" rows="3" placeholder="<?= e(__('admin.custom_html.instruction_hint')) ?>"><?= e($val('custom_html_instruction')) ?></textarea>
      </label>
    </div>
    <div class="apx-form-actions" style="margin:8px 0 4px;">
      <button type="button" class="apx-btn" id="apx-gen-prompt"><?= e(__('admin.custom_html.generate_prompt')) ?></button>
      <button type="button" class="apx-btn" id="apx-copy-prompt"><?= e(__('admin.custom_html.copy_prompt')) ?></button>
      <span id="apx-ch-status" class="apx-text-muted" style="margin-left:8px;opacity:0;transition:opacity .2s;"></span>
    </div>
    <textarea class="apx-textarea" id="apx-prompt" rows="10" readonly placeholder="<?= e(__('admin.custom_html.prompt_placeholder')) ?>"><?= e($prompt) ?></textarea>
    <p class="apx-card__hint"><?= e(__('admin.custom_html.prompt_help')) ?></p>
  </div>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.custom_html.group.interface')) ?></div>
    <label class="apx-field apx-field--wide">
      <span class="apx-field__label"><?= e(__('admin.custom_html.interface')) ?></span>
      <textarea class="apx-textarea" name="custom_html_interface" rows="6" placeholder="<?= e(__('admin.custom_html.interface_hint')) ?>"><?= e($ifaceVal) ?></textarea>
    </label>
    <p class="apx-card__hint"><?= e(__('admin.custom_html.interface_help')) ?></p>
  </div>

  <div class="apx-card" style="margin-bottom:16px;">
    <div class="apx-card__title"><?= e(__('admin.custom_html.group.content')) ?></div>
    <label class="apx-field apx-field--wide">
      <span class="apx-field__label"><?= e(__('admin.custom_html.content')) ?></span>
      <textarea class="apx-textarea" name="custom_html_content" rows="10" placeholder="<?= e(__('admin.custom_html.content_hint')) ?>"><?= e($val('custom_html_content')) ?></textarea>
    </label>
    <p class="apx-card__hint"><?= e(__('admin.custom_html.content_help')) ?></p>
  </div>

  <button class="apx-btn apx-btn--primary" type="submit"><?= e(__('common.action.save')) ?></button>
</form>

<script type="text/plain" id="apx-manifest"><?= e($manifest) ?></script>
<script type="text/plain" id="apx-placeholders"><?= e($phText) ?></script>
<script>
(function () {
  var form = document.getElementById('apx-custom-html');
  if (!form) return;
  var manifestEl = document.getElementById('apx-manifest');
  var phEl = document.getElementById('apx-placeholders');
  var manifest = manifestEl ? manifestEl.textContent : '';
  var phs = phEl ? phEl.textContent : '';

  function flash(msg) {
    var s = document.getElementById('apx-ch-status');
    if (!s) return;
    s.textContent = msg;
    s.style.opacity = 1;
    setTimeout(function () { s.style.opacity = 0; }, 1800);
  }

  function buildPrompt() {
    var inst = form.querySelector('[name=custom_html_instruction]').value;
    var iface = (form.querySelector('[name=custom_html_interface]').value || '').trim();
    var ifaceText = iface || manifest;
    var header = "【角色】你是一名前端工程师，为 APX 社交平台生成一段自定义前端 HTML 片段，"
      + "用于注入到站点的前台页面（信息流/发现/群组/私聊/个人主页等）。\n\n"
      + "【平台接口说明】\n" + ifaceText + "\n\n"
      + "【可用占位符（渲染时由站点替换，务必只用这些）】\n" + phs + "\n\n"
      + "【生成规则】\n"
      + "1. 仅输出纯 HTML 片段，不要 <html>/<head>/<body>，不要 <script>，不要外链脚本/CDN。\n"
      + "2. 只使用上面列出的占位符，不要臆造未知占位符；链接用 {{url.*}}，用户数据用 {{user.*}}。\n"
      + "3. 样式可用内联 style 或复用 .apx-card/.apx-btn 等类，保持响应式与玻璃拟态风格。\n"
      + "4. 保持轻量，不要遮挡核心操作；不要包含任何外部网络请求。\n\n"
      + "【管理员的诉求】\n" + (inst.trim() || "（未填写，请根据平台能力自由发挥）") + "\n";
    document.getElementById('apx-prompt').value = header;
  }

  var genBtn = document.getElementById('apx-gen-prompt');
  if (genBtn) genBtn.addEventListener('click', buildPrompt);

  var copyBtn = document.getElementById('apx-copy-prompt');
  if (copyBtn) copyBtn.addEventListener('click', function () {
    var t = document.getElementById('apx-prompt');
    t.select();
    if (navigator.clipboard) {
      navigator.clipboard.writeText(t.value).then(function () { flash('<?= e(__('admin.custom_html.copied')) ?>'); });
    } else {
      try { document.execCommand('copy'); flash('<?= e(__('admin.custom_html.copied')) ?>'); } catch (e) {}
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form);
    fetch(form.getAttribute('action'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.code === 0) {
          flash('<?= e(__('admin.custom_html.saved')) ?>');
          if (window.toast && toast.success) toast.success('<?= e(__('admin.custom_html.saved')) ?>');
        } else {
          var msg = (d && d.message) ? d.message : 'error';
          flash(msg);
          if (window.toast && toast.error) toast.error(msg);
        }
      })
      .catch(function () { flash('network error'); });
  });
})();
</script>
