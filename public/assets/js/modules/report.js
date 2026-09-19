// APX · 举报入口（全局）。任意页面放置 [data-report][data-report-type][data-report-id] 即可。
import { delegate, ready } from '../core/dom.js';
import * as http from '../core/http.js';
import * as modal from '../core/modal.js';
import { toast } from '../core/toast.js';

const t = (k) => (window.apx ? window.apx.i18n.t(k) : k);
const REASONS = ['spam', 'harassment', 'porn', 'violence', 'illegal', 'misinfo', 'other'];

function openDialog(type, id) {
  const options = REASONS.map((r) => `<option value="${r}">${t('report.reason.' + r)}</option>`).join('');
  const node = modal.open(`
    <div class="apx-modal__title">${t('report.title')}</div>
    <div class="apx-modal__body" style="margin:8px 0 12px;">
      <label class="apx-field">
        <span class="apx-field__label">${t('report.reason_label')}</span>
        <select class="apx-input" name="reason">${options}</select>
      </label>
      <label class="apx-field">
        <span class="apx-field__label">${t('report.detail_label')}</span>
        <textarea class="apx-textarea" name="detail" rows="3" maxlength="500" placeholder="${t('report.detail_placeholder')}"></textarea>
      </label>
      <div class="apx-text-faint" style="font-size:12px;">${t('report.disclaimer')}</div>
    </div>
    <div class="apx-modal__foot">
      <button class="apx-btn apx-btn--ghost apx-btn--sm" data-modal-close>${t('common.cancel')}</button>
      <button class="apx-btn apx-btn--danger apx-btn--sm" data-submit>${t('report.submit')}</button>
    </div>`);

  const btn = node.querySelector('[data-submit]');
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    try {
      const res = await http.post('/api/report', {
        target_type: type,
        target_id: id,
        reason: node.querySelector('[name="reason"]').value,
        detail: node.querySelector('[name="detail"]').value,
      });
      toast.success(res.message);
      modal.close();
    } catch (err) {
      toast.error(err.message);
      btn.disabled = false;
    }
  });
}

ready(() => {
  delegate(document, 'click', '[data-report]', (e, el) => {
    e.preventDefault();
    const type = el.getAttribute('data-report-type') || 'post';
    const id = parseInt(el.getAttribute('data-report-id') || '0', 10);
    if (!id) return;
    openDialog(type, id);
  });
});
