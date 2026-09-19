// APX · i18n (client). Dictionary injected via window.APX_I18N by layout.
let dict = {};
export function init(dictArg) { dict = dictArg || {}; }
export function setLocale(locale) { window.APX_LOCALE = locale; }
export function t(key, params = {}) {
  let val = dict[key];
  if (val === undefined) val = key;
  if (typeof val === 'string' && Object.keys(params).length) {
    for (const [k, v] of Object.entries(params)) {
      val = val.replace(new RegExp(':' + k + '\\b', 'g'), v);
    }
  }
  return val;
}
export function all() { return dict; }
