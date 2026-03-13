/* C:\wamp64\www\pactopia360_erp\public\assets\admin\js\admin-header.js */
/* P360 Admin Header v4.1 */
/* FIX: quitar doble control del sidebar; el sidebar lo maneja admin-sidebar.js */

(function () {
  'use strict';

  const root = document.documentElement;
  const header = document.getElementById('topbar');

  if (!header) return;

  const qs = function (sel, el) {
    return (el || document).querySelector(sel);
  };

  const qsa = function (sel, el) {
    return Array.from((el || document).querySelectorAll(sel));
  };

  const details = qsa('details.p360-dd', header);

  details.forEach(function (detail) {
    detail.addEventListener('toggle', function () {
      if (!detail.open) return;

      details.forEach(function (other) {
        if (other !== detail) other.open = false;
      });
    });
  });

  document.addEventListener('click', function (e) {
    if (header.contains(e.target)) return;

    details.forEach(function (detail) {
      detail.open = false;
    });
  }, { capture: true });

  const btnTheme = document.getElementById('btnTheme');
  const themeLabel = document.getElementById('themeLabel');

  function currentTheme() {
    const themeFromData = root.dataset.theme;
    if (themeFromData === 'dark' || themeFromData === 'light') {
      return themeFromData;
    }

    return root.classList.contains('theme-dark') ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';

    root.dataset.theme = nextTheme;
    root.classList.toggle('theme-dark', nextTheme === 'dark');
    root.classList.toggle('theme-light', nextTheme !== 'dark');

    try {
      localStorage.setItem('p360.theme', nextTheme);
    } catch (_) {}
  }

  function paintThemeLabel() {
    const theme = currentTheme();

    if (themeLabel) {
      themeLabel.textContent = theme === 'dark' ? 'Modo oscuro' : 'Modo claro';
    }

    if (btnTheme) {
      btnTheme.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }
  }

  paintThemeLabel();

  if (btnTheme) {
    btnTheme.addEventListener('click', function () {
      if (window.P360 && typeof window.P360.toggleTheme === 'function') {
        window.P360.toggleTheme();
      } else {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
      }

      paintThemeLabel();
    });
  }

  const hbUrl = header.getAttribute('data-heartbeat-url') || '';
  const hbDot = document.getElementById('hbDot');

  async function ping() {
    if (!hbUrl || !hbDot) return;

    try {
      const response = await fetch(hbUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      hbDot.classList.remove('ok', 'warn', 'fail');

      if (response.ok) {
        hbDot.classList.add('ok');
      } else {
        hbDot.classList.add('warn');
      }
    } catch (_) {
      hbDot.classList.remove('ok', 'warn');
      hbDot.classList.add('fail');
    }
  }

  ping();
  setInterval(ping, 15000);
})();