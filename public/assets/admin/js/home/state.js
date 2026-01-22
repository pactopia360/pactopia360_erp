// public/assets/admin/js/home/state.js
let _cache = null;
let _lastQueryKey = '';

let _controller = null;

export function setCache(data) { _cache = data; }
export function cache() { return _cache; }

export function setLastQueryKey(k) { _lastQueryKey = String(k || ''); }
export function lastQueryKey() { return _lastQueryKey; }

export function newAbortController() {
  try { if (_controller) _controller.abort(); } catch (_) {}
  _controller = new AbortController();
  return _controller;
}

export function getAbortSignal() {
  return _controller?.signal;
}

export function abortCurrent() {
  try { _controller?.abort(); } catch (_) {}
}
