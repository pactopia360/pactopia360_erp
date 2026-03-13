/* C:\wamp64\www\pactopia360_erp\public\assets\admin\js\admin-layout.js */
/* P360 Admin Layout · externalized JS */

(function () {
  'use strict';

  const meta = document.querySelector('meta[name="csrf-token"]');
  const token = meta ? meta.getAttribute('content') : '';

  if (token && typeof window.fetch === 'function' && !window.__p360_fetch_csrf__) {
    window.__p360_fetch_csrf__ = 1;
    const nativeFetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
      init = init || {};

      const method = String(init.method || 'GET').toUpperCase();
      const headers = new Headers(init.headers || {});

      headers.set('X-Requested-With', 'XMLHttpRequest');

      if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
        if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', token);
        if (!headers.has('X-XSRF-TOKEN')) headers.set('X-XSRF-TOKEN', token);
      }

      init.headers = headers;
      if (!init.credentials) init.credentials = 'same-origin';

      return nativeFetch(input, init);
    };
  }
})();

(function () {
  'use strict';

  const root = document.documentElement;
  if (root.classList.contains('p360-is-modal')) return;

  const progress = document.getElementById('p360-progress');
  const loading  = document.getElementById('p360-loading');
  const alerts   = document.getElementById('p360-alerts');
  const cmd      = document.getElementById('p360-cmd');
  const cmdIn    = document.getElementById('p360-cmd-input');

  window.P360 = window.P360 || {};

  window.P360.setTheme = function (theme) {
    if (!theme) return;

    root.dataset.theme = theme;

    const isDark = theme === 'dark';
    root.classList.toggle('theme-dark', isDark);
    root.classList.toggle('theme-light', !isDark);

    try {
      localStorage.setItem('p360.theme', theme);
    } catch (_) {}
  };

  window.P360.toggleTheme = function () {
    window.P360.setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
  };

  window.P360.toast = function (msg, opts) {
    opts = opts || {};
    if (!alerts) return;

    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = '<div style="flex:1">' + (msg || '') + '</div><button class="x" aria-label="Cerrar">×</button>';

    alerts.appendChild(el);

    const close = function () {
      el.style.opacity = '0';
      setTimeout(function () {
        el.remove();
      }, 220);
    };

    const btn = el.querySelector('.x');
    if (btn) btn.onclick = close;

    setTimeout(close, opts.timeout || 4200);
  };

  window.P360.loading = {
    show: function () {
      if (loading) loading.style.display = 'grid';
    },
    hide: function () {
      if (loading) loading.style.display = 'none';
    }
  };

  window.P360.progress = {
    start: function () {
      if (!progress) return;

      progress.style.opacity = '1';
      progress.style.width = '25%';

      (function tick() {
        if (progress.style.opacity !== '1') return;
        const currentWidth = parseFloat(progress.style.width) || 0;
        progress.style.width = Math.min(currentWidth + Math.random() * 18, 90) + '%';
        setTimeout(tick, 180);
      })();
    },

    done: function () {
      if (!progress) return;

      progress.style.width = '100%';
      setTimeout(function () {
        progress.style.opacity = '0';
        progress.style.width = '0%';
      }, 250);
    }
  };

  window.P360.focusSearch = function () {
    const el = document.querySelector('#globalSearch, form[role="search"] input[type="search"], input[type="search"][name="q"]');
    if (el) {
      el.focus();
      if (typeof el.select === 'function') el.select();
    }
  };

  window.P360.openCmd = function () {
    if (!cmd) return;

    cmd.style.display = 'grid';

    if (cmdIn) {
      cmdIn.value = '';
      setTimeout(function () {
        cmdIn.focus();
      }, 10);
    }
  };

  window.P360.closeCmd = function () {
    if (!cmd) return;
    cmd.style.display = 'none';
  };

  function syncHeaderHeight() {
    const topbar = document.getElementById('p360-topbar');
    if (!topbar) return;

    const height = Math.max(48, Math.round(topbar.getBoundingClientRect().height));
    root.style.setProperty('--header-h', height + 'px');
  }

  window.addEventListener('load', function () {
    requestAnimationFrame(syncHeaderHeight);
  }, { once: true });

  window.addEventListener('resize', function () {
    requestAnimationFrame(syncHeaderHeight);
  });

  setTimeout(syncHeaderHeight, 120);

  try {
    const storedTheme = localStorage.getItem('p360.theme');
    if (storedTheme) window.P360.setTheme(storedTheme);
  } catch (_) {}

  window.addEventListener('keydown', function (e) {
    const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;

    if (ctrl && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      window.P360.focusSearch();
    }

    if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) {
      e.preventDefault();
      window.P360.focusSearch();
    }

    if (ctrl && e.key.toLowerCase() === 'p') {
      e.preventDefault();
      window.P360.openCmd();
    }

    if (e.key === 'Escape' && cmd && cmd.style.display === 'grid') {
      e.preventDefault();
      window.P360.closeCmd();
    }
  });

  if (cmd) {
    cmd.addEventListener('click', function (ev) {
      if (ev.target === cmd) {
        window.P360.closeCmd();
      }
    });
  }

  (function bindStickyPageHeader() {
    const pageHeader = document.getElementById('page-header');
    if (!pageHeader) return;

    function onScroll() {
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      pageHeader.classList.toggle('affix-shadow', y > 6);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  })();
})();