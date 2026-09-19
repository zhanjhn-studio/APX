// APX · Tiny reactive store / event bus
const state = {};
const subs = {};

export function set(key, value) {
  state[key] = value;
  (subs[key] || []).forEach((fn) => fn(value));
}
export function get(key) { return state[key]; }
export function subscribe(key, fn) {
  subs[key] = subs[key] || [];
  subs[key].push(fn);
  return () => { subs[key] = subs[key].filter((f) => f !== fn); };
}

// global event bus
const bus = {};
export function emit(name, payload) { (bus[name] || []).forEach((f) => f(payload)); }
export function on(name, fn) { bus[name] = bus[name] || []; bus[name].push(fn); return () => off(name, fn); }
export function off(name, fn) { bus[name] = (bus[name] || []).filter((f) => f !== fn); }
