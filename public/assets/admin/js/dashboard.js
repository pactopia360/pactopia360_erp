(function () {
  const body = document.body;

  /* ========= Tema (persistente) ========= */
  const THEME_KEY = 'p360_theme'; // mantenemos tu clave
  const btnTheme = document.getElementById('themeToggle');

  function applyTheme(theme) {
    if (theme === 'light') {
      body.classList.add('theme-light');
      if (btnTheme) {
        btnTheme.querySelector('.icon').textContent = '🌞';
        btnTheme.querySelector('.label').textContent = 'Modo claro';
      }
    } else {
      body.classList.remove('theme-light');
      if (btnTheme) {
        btnTheme.querySelector('.icon').textContent = '🌙';
        btnTheme.querySelector('.label').textContent = 'Modo oscuro';
      }
    }
    // avisa a las vistas (Home) para re-colorear charts
    window.dispatchEvent(new CustomEvent('p360:theme', { detail: theme }));
  }

  const savedTheme = localStorage.getItem(THEME_KEY) || 'dark';
  applyTheme(savedTheme);

  if (btnTheme) {
    btnTheme.addEventListener('click', () => {
      const next = body.classList.contains('theme-light') ? 'dark' : 'light';
      localStorage.setItem(THEME_KEY, next);
      applyTheme(next);
    });
  }

  /* ========= Sidebar (desktop colapsable / móvil overlay) ========= */
  const mql = window.matchMedia('(min-width: 992px)'); // mismo breakpoint que CSS
  const btnDesktop = document.getElementById('sidebarToggle');
  const btnMobile  = document.getElementById('sidebarToggleMobile');
  const sidebar    = document.getElementById('sidebar');

  const SIDEBAR_KEY     = 'p360.sidebar';
  const SIDEBAR_KEY_OLD = 'p360_sidebar'; // compat con tu clave anterior

  const isDesktop = () => mql.matches;

  const readPref = () =>
    localStorage.getItem(SIDEBAR_KEY) ||
    localStorage.getItem(SIDEBAR_KEY_OLD) ||
    'expanded';

  const writePref = (v) => {
    localStorage.setItem(SIDEBAR_KEY, v);
    localStorage.removeItem(SIDEBAR_KEY_OLD);
  };

  function applySidebarFromStorage() {
    const pref = readPref();
    if (isDesktop()) {
      body.classList.toggle('sidebar-collapsed', pref === 'collapsed');
      body.classList.remove('sidebar-open');
    } else {
      body.classList.remove('sidebar-collapsed');
    }
  }

  applySidebarFromStorage();
  (mql.addEventListener ? mql.addEventListener('change', applySidebarFromStorage)
                        : window.addEventListener('resize', applySidebarFromStorage));

  if (btnDesktop) {
    btnDesktop.addEventListener('click', () => {
      if (!isDesktop()) return;
      body.classList.toggle('sidebar-collapsed');
      writePref(body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
    });
  }

  if (btnMobile) {
    btnMobile.addEventListener('click', () => {
      if (isDesktop()) return;
      body.classList.toggle('sidebar-open');
      btnMobile.setAttribute('aria-expanded', body.classList.contains('sidebar-open') ? 'true' : 'false');
    });
  }

  // Cerrar overlay en móvil al hacer click fuera o con Esc
  document.addEventListener('click', (e) => {
    if (!body.classList.contains('sidebar-open')) return;
    if (!sidebar) return;
    const inside = sidebar.contains(e.target) || (btnMobile && btnMobile.contains(e.target));
    if (!inside) {
      body.classList.remove('sidebar-open');
      btnMobile && btnMobile.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', (e)=>{
    const isMac = navigator.platform.toUpperCase().includes('MAC');
    if ((isMac ? e.metaKey : e.ctrlKey) && e.key.toLowerCase() === 'k'){
      const s = document.getElementById('globalSearch');
      if (s){ e.preventDefault(); s.focus(); s.select(); }
    }
  });
  

  /* ========= Header: sombra al hacer scroll ========= */
  const header = document.querySelector('.header');
  function onScroll() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 6);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
