// public/assets/admin/js/home/helpers.js
export function $(sel, root = document) {
  return root.querySelector(sel);
}

export function $all(sel, root = document) {
  return Array.from(root.querySelectorAll(sel));
}

export function debounce(fn, wait = 120) {
  let t = null;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), wait);
  };
}

export function money(v) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0
  }).format(Number(v || 0));
}

export function num(v) {
  return new Intl.NumberFormat('es-MX').format(Number(v || 0));
}
