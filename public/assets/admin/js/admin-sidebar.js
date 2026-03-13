/* C:\wamp64\www\pactopia360_erp\public\assets\admin\js\admin-sidebar.js */
/* Pactopia Sidebar vNext */
/* FIX FINAL:
   - sincroniza el ancho REAL del sidebar con el layout
   - prioriza la ruta activa sobre localStorage
   - evita que el main quede debajo del sidebar
   - desktop siempre expandido, mobile overlay
*/

(function (w, d) {
  'use strict';

  w.P360 = w.P360 || {};

  const SB   = d.getElementById('nebula-sidebar');
  const M1   = d.getElementById('nsMenuCurated');
  const M2   = d.getElementById('nsMenuAuto');
  const BCK  = d.getElementById('nsBackdrop');
  const HBTN = d.getElementById('btnSidebar');

  if (!SB || !M1 || !M2) return;

  const root = d.documentElement;
  const body = d.body;

  const KEY_OPEN   = 'p360.sidebar.open.vnext';
  const KEY_GROUPS = 'p360.sidebar.groups.vnext';
  const KEY_PINS   = 'p360.sidebar.pins.vnext';
  const KEY_TAB    = 'p360.sidebar.tab.vnext';
  const KEY_MOD    = 'p360.sidebar.module.vnext';

  const railButtons = Array.from(SB.querySelectorAll('.p360-rail-btn'));
  const modulesCur  = Array.from(M1.querySelectorAll('.p360-module[data-module]'));
  const tabs        = Array.from(SB.querySelectorAll('.p360-segmented-btn[data-tab]'));
  const panelTitle  = d.getElementById('nsPanelTitle');
  const panelSub    = d.getElementById('nsPanelSubtitle');
  const search      = d.getElementById('nsSearch');
  const favWrap     = d.getElementById('nsFavs');
  const favsToggle  = d.getElementById('nsFavsToggle');
  const themeToggle = d.getElementById('nsThemeToggle');

  let onlyFavs = false;
  let currentTab = load(KEY_TAB, 'curated');
  let currentModule = load(KEY_MOD, '');
  let groups = parseJSON(load(KEY_GROUPS, '{}'), {});
  let searchTerm = '';

  function isDesktop() {
    return w.matchMedia('(min-width:1024px)').matches;
  }

  function load(key, fallback) {
    try {
      const v = localStorage.getItem(key);
      return v === null ? fallback : v;
    } catch (_) {
      return fallback;
    }
  }

  function save(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (_) {}
  }

  function parseJSON(value, fallback) {
    try {
      return JSON.parse(value);
    } catch (_) {
      return fallback;
    }
  }

  function getPins() {
    return parseJSON(load(KEY_PINS, '[]'), []);
  }

  function setPins(arr) {
    save(KEY_PINS, JSON.stringify(arr || []));
  }

  function inferActiveModule() {
    const activeLink =
      SB.querySelector('.p360-link[aria-current="page"]') ||
      SB.querySelector('.p360-link.is-active') ||
      SB.querySelector('.p360-module.is-route-active .p360-link');

    if (!activeLink) return '';

    const host = activeLink.closest('.p360-module[data-module]');
    return host ? (host.getAttribute('data-module') || '') : '';
  }

  function syncSidebarMetrics() {
    w.requestAnimationFrame(() => {
      if (!SB) return;

      if (isDesktop()) {
        const rect = SB.getBoundingClientRect();
        const width = Math.max(0, Math.round(rect.width));

        if (width > 0) {
          root.style.setProperty('--sidebar-w', width + 'px');
          root.style.setProperty('--sidebar-offset', width + 'px');
        }
      } else {
        root.style.setProperty('--sidebar-offset', '0px');
      }
    });
  }

  function reflectShell() {
    root.classList.remove('sidebar-collapsed');

    if (isDesktop()) {
      body.classList.remove('sidebar-open');
      save(KEY_OPEN, '0');
    } else {
      body.classList.toggle('sidebar-open', load(KEY_OPEN, '0') === '1');
      root.style.setProperty('--sidebar-offset', '0px');
    }

    if (HBTN) {
      HBTN.setAttribute('aria-expanded', body.classList.contains('sidebar-open') ? 'true' : 'false');
    }

    syncSidebarMetrics();
  }

  w.P360.sidebar = {
    isCollapsed() {
      return false;
    },
    setCollapsed() {
      reflectShell();
    },
    toggle() {
      if (!isDesktop()) {
        this.openMobile(load(KEY_OPEN, '0') !== '1');
      }
    },
    openMobile(flag = true) {
      if (!isDesktop()) {
        save(KEY_OPEN, flag ? '1' : '0');
        reflectShell();
      }
    },
    closeMobile() {
      this.openMobile(false);
    },
    sync() {
      syncSidebarMetrics();
    }
  };

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getModuleMeta(moduleName) {
    const mod = modulesCur.find((m) => (m.getAttribute('data-module') || '') === moduleName);
    if (!mod) {
      return { label: 'Módulos', summary: 'Navegación del panel' };
    }

    return {
      label: mod.getAttribute('data-label') || 'Módulos',
      summary: mod.getAttribute('data-summary') || 'Navegación del panel'
    };
  }

  function setPanelHeader(moduleName) {
    const meta = getModuleMeta(moduleName);
    if (panelTitle) panelTitle.textContent = meta.label;
    if (panelSub) panelSub.textContent = meta.summary;
  }

  function markRailActive(moduleName) {
    railButtons.forEach((btn) => {
      const on = (btn.getAttribute('data-module') || '') === moduleName;
      btn.classList.toggle('is-active', on);
    });
  }

  function setActiveModule(moduleName) {
    if (!moduleName) return;

    currentModule = moduleName;
    save(KEY_MOD, moduleName);

    modulesCur.forEach((mod) => {
      const on = (mod.getAttribute('data-module') || '') === moduleName;
      mod.classList.toggle('is-active', on);
      mod.style.display = on ? '' : 'none';
    });

    markRailActive(moduleName);
    setPanelHeader(moduleName);
    applyFilter(searchTerm);
    syncSidebarMetrics();
  }

  function showTab(tab) {
    currentTab = tab === 'auto' ? 'auto' : 'curated';
    save(KEY_TAB, currentTab);

    tabs.forEach((t) => {
      const active = (t.getAttribute('data-tab') || '') === currentTab;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    M1.hidden = currentTab !== 'curated';
    M2.hidden = currentTab !== 'auto';

    if (currentTab === 'curated') {
      const activeModule = inferActiveModule();
      if (activeModule) {
        currentModule = activeModule;
      } else if (!currentModule) {
        currentModule = railButtons[0] ? (railButtons[0].getAttribute('data-module') || 'home') : 'home';
      }

      setActiveModule(currentModule);
    } else {
      railButtons.forEach((btn) => btn.classList.remove('is-active'));
      if (panelTitle) panelTitle.textContent = 'Explorar';
      if (panelSub) panelSub.textContent = 'Todas las rutas admin.*';
      applyFilter(searchTerm);
    }

    syncSidebarMetrics();
  }

  function renderPins() {
    if (!favWrap) return;

    const pins = getPins();
    favWrap.innerHTML = '';

    if (!pins.length) {
      favWrap.hidden = true;
      return;
    }

    favWrap.hidden = false;

    const sec = d.createElement('div');
    sec.className = 'p360-section';
    sec.textContent = 'FAVORITOS';

    const list = d.createElement('div');
    list.className = 'p360-fav-list';

    pins.forEach((pin) => {
      const a = d.createElement('a');
      a.className = 'p360-fav-link';
      a.href = pin.url || '#';
      a.setAttribute('data-title', pin.t || pin.url || '');
      a.innerHTML = '<span class="star">★</span><span>' + escapeHtml(pin.t || pin.url || '') + '</span>';
      list.appendChild(a);
    });

    favWrap.appendChild(sec);
    favWrap.appendChild(list);
  }

  function syncFavButtons() {
    const pinSet = new Set(getPins().map((p) => p.url));

    [M1, M2].forEach((menu) => {
      menu.querySelectorAll('.p360-item').forEach((row) => {
        const a = row.querySelector('.p360-link');
        const b = row.querySelector('.p360-fav');

        if (!a || !b || a.dataset.placeholder === '1') return;

        const url = a.getAttribute('href') || '#';
        const on = pinSet.has(url);

        b.classList.toggle('is-active', on);
        b.textContent = on ? '★' : '☆';
        b.setAttribute('aria-label', on ? 'Quitar de favoritos' : 'Agregar a favoritos');
        b.setAttribute('title', on ? 'Quitar de favoritos' : 'Agregar a favoritos');
      });
    });
  }

  function applyDetailsState(activeMenu) {
    activeMenu.querySelectorAll('details.p360-group[data-key]').forEach((det) => {
      const menuTab = activeMenu.getAttribute('data-tab') || currentTab;
      const key = menuTab + ':' + (det.getAttribute('data-key') || '');

      if (!searchTerm) {
        if (Object.prototype.hasOwnProperty.call(groups, key)) {
          det.open = !!groups[key];
        } else {
          const sum = det.querySelector(':scope > summary');
          det.open = !!(sum && sum.classList.contains('is-active'));
        }
      }

      const sum = det.querySelector(':scope > summary');
      if (sum) {
        sum.setAttribute('aria-expanded', det.open ? 'true' : 'false');
      }
    });
  }

  function applyFilter(q) {
    searchTerm = String(q || '').trim().toLowerCase();
    const pinUrls = new Set(getPins().map((p) => p.url));
    const activeMenu = currentTab === 'auto' ? M2 : M1;

    if (currentTab === 'curated') {
      modulesCur.forEach((mod) => {
        const moduleName = mod.getAttribute('data-module') || '';
        const showModule = !searchTerm ? moduleName === currentModule : moduleName === currentModule;
        mod.style.display = showModule ? '' : 'none';
      });
    }

    activeMenu.querySelectorAll('.p360-item').forEach((row) => {
      const a = row.querySelector('.p360-link');
      if (!a) return;

      const txt = (a.getAttribute('data-txt') || '').toLowerCase();
      const title = (a.getAttribute('data-title') || '').toLowerCase();
      const url = a.getAttribute('href') || '';
      const matchesSearch = !searchTerm || txt.includes(searchTerm) || title.includes(searchTerm);
      const matchesFav = !onlyFavs || pinUrls.has(url);

      row.style.display = matchesSearch && matchesFav ? '' : 'none';
    });

    activeMenu.querySelectorAll('details.p360-group').forEach((det) => {
      const childItems = Array.from(det.querySelectorAll(':scope > .p360-children .p360-item'));
      const visibleItems = childItems.some((item) => item.style.display !== 'none');

      const childGroups = Array.from(det.querySelectorAll(':scope > .p360-children > details.p360-group'));
      const visibleGroups = childGroups.some((group) => group.style.display !== 'none');

      const visible = visibleItems || visibleGroups || !searchTerm;
      det.style.display = visible ? '' : 'none';

      if (searchTerm && (visibleItems || visibleGroups)) {
        det.open = true;
      }
    });

    activeMenu.querySelectorAll('.p360-section').forEach((sec) => {
      let next = sec.nextElementSibling;
      let hasVisible = false;

      while (next && !next.classList.contains('p360-section')) {
        if (next.style.display !== 'none') {
          hasVisible = true;
          break;
        }
        next = next.nextElementSibling;
      }

      sec.style.display = hasVisible ? '' : 'none';
    });

    applyDetailsState(activeMenu);
    syncSidebarMetrics();
  }

  function togglePinFromButton(btn) {
    const row = btn.closest('.p360-item');
    const link = row ? row.querySelector('.p360-link') : null;
    if (!link) return;

    const title = link.getAttribute('data-title') || link.textContent.trim();
    const url = link.getAttribute('href') || '#';

    let pins = getPins();
    const idx = pins.findIndex((p) => p.url === url);

    if (idx >= 0) {
      pins.splice(idx, 1);
    } else {
      pins.unshift({ t: title, url: url });
    }

    setPins(pins);
    renderPins();
    syncFavButtons();
    applyFilter(searchTerm);
  }

  function initGroupPersistence(menu) {
    const menuTab = menu.getAttribute('data-tab') || 'curated';

    menu.querySelectorAll('details.p360-group[data-key]').forEach((det) => {
      const key = menuTab + ':' + (det.getAttribute('data-key') || '');
      const sum = det.querySelector(':scope > summary');

      if (Object.prototype.hasOwnProperty.call(groups, key)) {
        det.open = !!groups[key];
      }

      if (sum) {
        sum.setAttribute('aria-expanded', det.open ? 'true' : 'false');
      }

      det.addEventListener('toggle', () => {
        groups[key] = det.open ? 1 : 0;
        save(KEY_GROUPS, JSON.stringify(groups));
        if (sum) {
          sum.setAttribute('aria-expanded', det.open ? 'true' : 'false');
        }
        syncSidebarMetrics();
      });
    });
  }

  railButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const moduleName = btn.getAttribute('data-module') || '';
      if (!moduleName) return;

      if (currentTab !== 'curated') {
        showTab('curated');
      }

      setActiveModule(moduleName);

      if (search) {
        search.value = '';
        applyFilter('');
      }

      if (!isDesktop()) {
        save(KEY_OPEN, '0');
        reflectShell();
      }
    });
  });

  tabs.forEach((tabBtn) => {
    tabBtn.addEventListener('click', () => {
      showTab(tabBtn.getAttribute('data-tab') || 'curated');
    });
  });

  if (search) {
    search.addEventListener('input', (e) => {
      applyFilter(e.target.value || '');
    });
  }

  if (favsToggle) {
    favsToggle.addEventListener('click', () => {
      onlyFavs = !onlyFavs;
      favsToggle.setAttribute('aria-pressed', onlyFavs ? 'true' : 'false');
      applyFilter(searchTerm);
    });
  }

  [M1, M2].forEach((menu) => {
    menu.addEventListener('click', (e) => {
      const placeholder = e.target.closest('.p360-link[data-placeholder="1"]');
      if (placeholder) {
        e.preventDefault();
        return;
      }

      const favBtn = e.target.closest('.p360-fav');
      if (favBtn) {
        e.preventDefault();
        e.stopPropagation();
        togglePinFromButton(favBtn);
        return;
      }

      const link = e.target.closest('.p360-link[href]');
      if (link && !isDesktop()) {
        save(KEY_OPEN, '0');
        reflectShell();
      }
    });
  });

  d.getElementById('nsToggle')?.addEventListener('click', () => {
    if (!isDesktop()) {
      w.P360.sidebar.toggle();
    }
  });

  themeToggle?.addEventListener('click', () => {
    if (w.P360 && typeof w.P360.toggleTheme === 'function') {
      w.P360.toggleTheme();
    } else {
      const dark = root.dataset.theme === 'dark';
      root.dataset.theme = dark ? 'light' : 'dark';
      root.classList.toggle('theme-dark', !dark);
      root.classList.toggle('theme-light', dark);
    }

    setTimeout(syncSidebarMetrics, 20);
  });

  BCK?.addEventListener('click', () => {
    w.P360.sidebar.closeMobile();
  }, { passive: true });

  if (HBTN) {
    HBTN.addEventListener('click', () => {
      if (!isDesktop()) {
        save(KEY_OPEN, load(KEY_OPEN, '0') === '1' ? '0' : '1');
        reflectShell();
      }
    });
  }

  w.addEventListener('keydown', (e) => {
    const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;

    if (ctrl && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (search) {
        search.focus();
        search.select();
      }
    }

    if (e.key === 'Escape' && !isDesktop()) {
      save(KEY_OPEN, '0');
      reflectShell();
    }
  });

  const mq = w.matchMedia('(min-width:1024px)');
  mq.addEventListener?.('change', reflectShell);
  w.addEventListener('resize', reflectShell);
  w.addEventListener('load', syncSidebarMetrics);
  w.addEventListener('pageshow', syncSidebarMetrics);

  initGroupPersistence(M1);
  initGroupPersistence(M2);

  renderPins();
  syncFavButtons();

  const routeModule = inferActiveModule();
  if (routeModule) {
    currentModule = routeModule;
    currentTab = 'curated';
    save(KEY_MOD, currentModule);
    save(KEY_TAB, currentTab);
  } else if (!currentModule) {
    currentModule = railButtons[0] ? (railButtons[0].getAttribute('data-module') || 'home') : 'home';
  }

  reflectShell();
  showTab(currentTab);
  setActiveModule(currentModule);
  applyFilter('');
  setTimeout(syncSidebarMetrics, 60);
  setTimeout(syncSidebarMetrics, 180);
})(window, document);