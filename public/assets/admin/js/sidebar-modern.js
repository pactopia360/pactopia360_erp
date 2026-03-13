document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const root = document.documentElement;
  const body = document.body;

  const sidebar = document.getElementById('p360-sidebar');
  if (!sidebar) return;

  const railItems = Array.from(sidebar.querySelectorAll('.sb-rail-item'));
  const modules = Array.from(sidebar.querySelectorAll('.sb-module'));
  const titleEl = sidebar.querySelector('.sb-title');
  const subtitleEl = sidebar.querySelector('.sb-subtitle');
  const searchInput = sidebar.querySelector('.sb-search');
  const mobileToggle = document.getElementById('btnSidebar');

  const KEY_ACTIVE_MODULE = 'p360.sidebarModern.activeModule.v1';

  const moduleTitles = {
    home: 'Home',
    empresas: 'Empresas',
    admin: 'Administración',
    billing: 'Billing SaaS',
    finanzas: 'Finanzas',
    config: 'Configuración',
    reportes: 'Reportes',
    perfil: 'Mi cuenta',
  };

  const moduleSubtitles = {
    home: 'Accesos rápidos del panel',
    empresas: 'Estructura empresarial y áreas',
    admin: 'Usuarios, clientes y operación',
    billing: 'Cobro, cuentas y facturación',
    finanzas: 'Ingresos, egresos y ventas',
    config: 'Parámetros e integraciones',
    reportes: 'KPIs y vistas ejecutivas',
    perfil: 'Cuenta y preferencias',
  };

  function saveActiveModule(moduleName) {
    try {
      localStorage.setItem(KEY_ACTIVE_MODULE, moduleName);
    } catch (e) {}
  }

  function loadActiveModule() {
    try {
      return localStorage.getItem(KEY_ACTIVE_MODULE) || '';
    } catch (e) {
      return '';
    }
  }

  function setHeader(moduleName) {
    if (titleEl) {
      titleEl.textContent = moduleTitles[moduleName] || 'Módulos';
    }
    if (subtitleEl) {
      subtitleEl.textContent = moduleSubtitles[moduleName] || 'Navegación del panel';
    }
  }

  function setActiveModule(moduleName) {
    if (!moduleName) return;

    const targetModule = sidebar.querySelector(`.sb-module[data-module="${moduleName}"]`);
    const targetButton = sidebar.querySelector(`.sb-rail-item[data-module="${moduleName}"]`);

    if (!targetModule || !targetButton) return;

    railItems.forEach((btn) => btn.classList.remove('active'));
    modules.forEach((mod) => mod.classList.remove('active'));

    targetButton.classList.add('active');
    targetModule.classList.add('active');

    setHeader(moduleName);
    saveActiveModule(moduleName);
  }

  function inferActiveModuleFromContent() {
    const currentActiveLink =
      sidebar.querySelector('.sb-module .sb-link.active') ||
      sidebar.querySelector('.sb-module .sb-link[aria-current="page"]');

    if (!currentActiveLink) return '';

    const currentModule = currentActiveLink.closest('.sb-module');
    return currentModule ? currentModule.getAttribute('data-module') || '' : '';
  }

  function filterCurrentModule(term) {
    const activeModule = sidebar.querySelector('.sb-module.active');
    if (!activeModule) return;

    const q = String(term || '').trim().toLowerCase();
    const links = Array.from(activeModule.querySelectorAll('.sb-link'));
    const groups = Array.from(activeModule.querySelectorAll('.sb-group'));

    links.forEach((link) => {
      const text = (link.textContent || '').toLowerCase();
      const match = !q || text.includes(q);
      link.style.display = match ? '' : 'none';
    });

    groups.forEach((group) => {
      let next = group.nextElementSibling;
      let hasVisible = false;

      while (next && !next.classList.contains('sb-group')) {
        if (
          next.classList.contains('sb-link') &&
          next.style.display !== 'none'
        ) {
          hasVisible = true;
        }
        next = next.nextElementSibling;
      }

      group.style.display = hasVisible || !q ? '' : 'none';
    });
  }

  railItems.forEach((btn) => {
    btn.addEventListener('click', () => {
      const moduleName = btn.dataset.module || '';
      setActiveModule(moduleName);

      if (searchInput) {
        searchInput.value = '';
        filterCurrentModule('');
      }

      if (window.innerWidth < 1024 && body.classList.contains('sidebar-open')) {
        body.classList.remove('sidebar-open');
        if (mobileToggle) {
          mobileToggle.setAttribute('aria-expanded', 'false');
        }
      }
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      filterCurrentModule(e.target.value || '');
    });
  }

  document.addEventListener('keydown', (e) => {
    const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;

    if (ctrl && e.key.toLowerCase() === 'k') {
      if (searchInput) {
        e.preventDefault();
        searchInput.focus();
        searchInput.select();
      }
    }

    if (e.key === 'Escape' && window.innerWidth < 1024) {
      body.classList.remove('sidebar-open');
      if (mobileToggle) {
        mobileToggle.setAttribute('aria-expanded', 'false');
      }
    }
  });

  const storedModule = loadActiveModule();
  const inferredModule = inferActiveModuleFromContent();
  const initialModule =
    inferredModule ||
    storedModule ||
    (railItems[0] ? railItems[0].dataset.module || 'home' : 'home');

  setActiveModule(initialModule);
  filterCurrentModule('');
});