(function () {
  'use strict';

  // ========= Migración de claves antiguas → nuevas (una sola vez) =========
  try {
    const LS = window.localStorage;

    // Tema: p360_theme -> p360-theme
    const oldTheme = LS.getItem('p360_theme'); // 'light' | 'dark'
    if (oldTheme && !LS.getItem('p360-theme')) {
      LS.setItem('p360-theme', oldTheme);
    }

    // Sidebar: p360.sidebar / p360_sidebar -> p360-sidebar ('1' colapsado | '0' expandido)
    const oldSb = LS.getItem('p360.sidebar') ?? LS.getItem('p360_sidebar');
    if (oldSb !== null && !LS.getItem('p360-sidebar')) {
      const v = (oldSb === 'collapsed' || oldSb === '1') ? '1' : '0';
      LS.setItem('p360-sidebar', v);
      // Limpia legacy
      LS.removeItem('p360.sidebar');
      LS.removeItem('p360_sidebar');
    }
  } catch (e) {
    // no-op
  }

  // ========= NO controlar tema ni sidebar aquí =========
  // (eso ya lo hace: preboot del <head> + scripts.blade.php)

  // ========= Header: sombra al hacer scroll (seguro) =========
  const header = document.querySelector('.header');
  function onScroll() {
    if (header) header.classList.toggle('header--shadow', window.scrollY > 6);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ========= A11y menor del botón de tema (sin lógica de toggle) =========
  const btnTheme = document.getElementById('themeToggle');
  if (btnTheme && !btnTheme.dataset.init) {
    btnTheme.dataset.init = '1';
    btnTheme.setAttribute('aria-label', 'Cambiar tema');
    btnTheme.title = 'Cambiar tema';
  }

  // (Espacio para lógica adicional del dashboard que NO toque tema/sidebar)
})();
