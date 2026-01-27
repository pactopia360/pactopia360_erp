// public/assets/admin/js/sat-ops-credentials.js
// P360 · Admin · SAT Ops · Credenciales (v4.0)
// - Drawer de detalle por fila (data-open-cred / #credDrawer)
// - Copy buttons (data-copy) y copy-from (data-copy-from="#id")
// - Password toggle en drawer (data-cred-pass-toggle)
// - Meta drawer (data-meta-btn / data-open-meta) + copiar meta
// - Kebab menu (data-menu)
// - Delete (data-delete-id) y delete desde drawer (data-cred-delete) via fetch DELETE si existe #p360OpsCred[data-rt-delete]
// - Ctrl+K enfoca búsqueda (#opsSearch)

(function () {
  'use strict';

  if (window.__P360_SAT_OPS_CRED_JS__) return;
  window.__P360_SAT_OPS_CRED_JS__ = true;

  const root = document.getElementById('p360OpsCred');
  const search = document.getElementById('opsSearch');

  // ===== Meta Drawer (existente) =====
  const metaDrawer = document.getElementById('metaDrawer');
  const metaTitleEl = document.getElementById('metaTitle');
  const metaPreEl = document.getElementById('metaPre');
  const btnCopyMeta = metaDrawer ? metaDrawer.querySelector('[data-copy-meta]') : null;

  // ===== Cred Drawer (nuevo) =====
  const credDrawer = document.getElementById('credDrawer');

  // Drawer close buttons/backdrops
  const credCloseSel = '[data-cred-close]';

  // Cred drawer fields
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  const credRfc = document.getElementById('credRfc');
  const credStatus = document.getElementById('credStatus');
  const credName = document.getElementById('credName');

  const credAccTitle = document.getElementById('credAccTitle');
  const credAccHint = document.getElementById('credAccHint');
  const credAccRef = document.getElementById('credAccRef');
  const credAccountId = document.getElementById('credAccountId');
  const credCuentaId = document.getElementById('credCuentaId');

  const credAccEmail = document.getElementById('credAccEmail');
  const credAccPhone = document.getElementById('credAccPhone');
  const credAccStatus = document.getElementById('credAccStatus');
  const credAccPlan = document.getElementById('credAccPlan');
  const credAccCreated = document.getElementById('credAccCreated');

  const credAccountLink = document.getElementById('credAccountLink');

  const credOriginTag = document.getElementById('credOriginTag');
  const credOriginHint = document.getElementById('credOriginHint');

  const credAlertsTag = document.getElementById('credAlertsTag');
  const credLastAlert = document.getElementById('credLastAlert');

  const credFilesTag = document.getElementById('credFilesTag');
  const credCerBtn = document.getElementById('credCerBtn');
  const credKeyBtn = document.getElementById('credKeyBtn');

  const credPassUi = document.getElementById('credPassUi');
  const credPassInput = document.getElementById('credPassInput');
  const credPassEmpty = document.getElementById('credPassEmpty');

  const credIdShort = document.getElementById('credIdShort');
  const credIdFull = document.getElementById('credIdFull');

  const credCreated = document.getElementById('credCreated');
  const credUpdated = document.getElementById('credUpdated');
  const credValidated = document.getElementById('credValidated');

  // runtime state
  let activeRowEl = null;
  let activeId = '';
  let activeRfc = '';
  let activeMeta = '{}';
  let activeMetaTitle = 'Meta';

  function toast(msg, kind) {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch (_) {}
    try { console.log('[P360]', msg); } catch (_) {}
    if (kind === 'error') alert(msg);

  }

  async function copyText(text, okMsg) {
    const val = String(text ?? '');
    try {
      await navigator.clipboard.writeText(val);
      if (okMsg) toast(okMsg, 'success');
      return true;
    } catch (_) {
      // fallback con textarea
      try {
        const ta = document.createElement('textarea');
        ta.value = val;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if (ok) {
          if (okMsg) toast(okMsg, 'success');
          return true;
        }
      } catch (_) {}
      toast('No se pudo copiar al portapapeles.', 'error');
      return false;
    }
  }

  function cssEscape(value) {
  const v = String(value ?? '');
  try {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(v);
  } catch (_) {}
  // fallback simple (suficiente para UUIDs/ids comunes)
  return v.replace(/"/g, '\\"').replace(/\\/g, '\\\\');
}


  // ===== Menús kebab =====
  function closeAllMenus(except) {
    const menus = document.querySelectorAll('[data-menu]');
    menus.forEach((m) => {
      if (except && except === m) return;
      const panel = m.querySelector('[data-menu-panel]');
      if (panel) panel.setAttribute('aria-hidden', 'true');
      m.classList.remove('is-open');
    });
  }

  function toggleMenu(menuWrap) {
    const panel = menuWrap.querySelector('[data-menu-panel]');
    if (!panel) return;
    const isHidden = panel.getAttribute('aria-hidden') !== 'false';
    closeAllMenus(menuWrap);
    panel.setAttribute('aria-hidden', isHidden ? 'false' : 'true');
    menuWrap.classList.toggle('is-open', isHidden);
  }

  // ===== Meta drawer =====
  function openMetaDrawer(title, text) {
    if (!metaDrawer) return;
    try {
      if (metaTitleEl) metaTitleEl.textContent = title || 'Meta';
      if (metaPreEl) metaPreEl.textContent = text || '{}';
      metaDrawer.setAttribute('aria-hidden', 'false');
      document.documentElement.style.overflow = 'hidden';
    } catch (_) {}
  }

  function closeMetaDrawer() {
    if (!metaDrawer) return;
    try {
      metaDrawer.setAttribute('aria-hidden', 'true');
      document.documentElement.style.overflow = '';
    } catch (_) {}
  }

  // ===== Cred drawer =====
  function isCredOpen() {
    return !!(credDrawer && credDrawer.getAttribute('aria-hidden') === 'false');
  }

  function openCredDrawer() {
    if (!credDrawer) return;
    try {
      credDrawer.setAttribute('aria-hidden', 'false');
      document.documentElement.style.overflow = 'hidden';
    } catch (_) {}
  }

  function closeCredDrawer() {
    if (!credDrawer) return;
    try {
      credDrawer.setAttribute('aria-hidden', 'true');
      document.documentElement.style.overflow = '';
    } catch (_) {}
    if (activeRowEl) activeRowEl.classList.remove('is-active');
    activeRowEl = null;
    activeId = '';
    activeRfc = '';
    activeMeta = '{}';
    activeMetaTitle = 'Meta';
  }

  function setText(el, val, dash = '—') {
    if (!el) return;
    const v = String(val ?? '').trim();
    el.textContent = v !== '' ? v : dash;
  }

  function setTag(el, text, cls) {
    if (!el) return;
    el.textContent = String(text ?? '—') || '—';
    try {
      const base = Array.from(el.classList).filter((c) => c === 'tag');
      el.className = base.join(' ') || 'tag';
      if (cls) el.classList.add(String(cls));
    } catch (_) {}
  }

  function setPill(el, text, cls) {
    if (!el) return;
    el.textContent = String(text ?? '—') || '—';
    try {
      const base = Array.from(el.classList).filter((c) => c === 'pill');
      el.className = base.join(' ') || 'pill';
      if (cls) el.classList.add(String(cls));
    } catch (_) {}
  }

  function safeAttr(el, name, value) {
    if (!el) return;
    try {
      if (value === null || value === undefined || value === '') el.removeAttribute(name);
      else el.setAttribute(name, String(value));
    } catch (_) {}
  }

  function show(el) { if (el) el.style.display = ''; }
  function hide(el) { if (el) el.style.display = 'none'; }

  function getRowFromTarget(t) {
    if (!t) return null;
    return t.closest('.tr[data-row], .tr[data-id]') || null;
  }

  function readRowData(row) {
    const g = (k) => row ? (row.getAttribute(k) || '') : '';
    return {
      id: g('data-id'),
      rfc: g('data-rfc'),
      name: g('data-name'),

      accTitle: g('data-acc-title'),
      accHint: g('data-acc-hint'),
      accRef: g('data-acc-ref'),
      accountId: g('data-account-id'),
      cuentaId: g('data-cuenta-id'),
      accountUrl: g('data-account-url'),

      accEmail: g('data-acc-email'),
      accPhone: g('data-acc-phone'),
      accStatus: g('data-acc-status'),
      accPlan: g('data-acc-plan'),
      accCreated: g('data-acc-created'),

      status: g('data-status'),
      statusCls: g('data-status-cls'),

      origin: g('data-origin'),
      originCls: g('data-origin-cls'),
      originHint: g('data-origin-hint'),

      files: g('data-files'),
      filesCls: g('data-files-cls'),
      hasCer: g('data-has-cer') === '1',
      hasKey: g('data-has-key') === '1',
      hasPass: g('data-has-pass') === '1',
      pass: g('data-pass'),
      cerUrl: g('data-cer-url'),
      keyUrl: g('data-key-url'),

      alerts: g('data-alerts'),
      alertsCls: g('data-alerts-cls'),
      lastAlert: g('data-last-alert'),
      lastAlertAgo: g('data-last-alert-ago'),

      created: g('data-created'),
      updated: g('data-updated'),
      validated: g('data-validated'),

      createdAgo: g('data-created-ago'),
      updatedAgo: g('data-updated-ago'),

      meta: g('data-meta'),
      metaTitle: g('data-meta-title')
    };
  }

  function fillCredDrawerFromRow(row) {
    if (!row) return;

    if (activeRowEl && activeRowEl !== row) activeRowEl.classList.remove('is-active');
    activeRowEl = row;
    activeRowEl.classList.add('is-active');

    const d = readRowData(row);

    activeId = d.id || '';
    activeRfc = d.rfc || '';
    activeMeta = d.meta || '{}';
    activeMetaTitle = d.metaTitle || 'Meta';

    setText(credRfc, d.rfc);
    setText(credName, d.name);
    setPill(credStatus, d.status || '—', d.statusCls || '');

    setText(credAccTitle, d.accTitle);
    setText(credAccHint, d.accHint);
    setText(credAccRef, d.accRef);
    setText(credAccountId, d.accountId);
    setText(credCuentaId, d.cuentaId);

    setText(credAccEmail, d.accEmail);
    setText(credAccPhone, d.accPhone);
    setText(credAccStatus, d.accStatus);
    setText(credAccPlan, d.accPlan);
    setText(credAccCreated, d.accCreated);


    if (credAccountLink) {
      const url = (d.accountUrl || '').trim();
      if (url) {
        credAccountLink.href = url;
        show(credAccountLink);
      } else {
        credAccountLink.href = '#';
        hide(credAccountLink);
      }
    }

    setTag(credOriginTag, d.origin || '—', d.originCls || '');
    setText(credOriginHint, d.originHint || '—');
    setTag(credAlertsTag, d.alerts || '—', d.alertsCls || '');

    if (credLastAlert) {
      const txt = (d.lastAlertAgo && d.lastAlertAgo !== '—') ? d.lastAlertAgo : (d.lastAlert || '—');
      credLastAlert.textContent = txt || '—';
      if (d.lastAlert && d.lastAlert !== '—') credLastAlert.setAttribute('title', d.lastAlert);
      else credLastAlert.removeAttribute('title');
    }

    setTag(credFilesTag, d.files || '—', d.filesCls || '');

    if (credCerBtn) {
      credCerBtn.href = d.cerUrl || '#';
      credCerBtn.classList.toggle('is-disabled', !d.hasCer);
      safeAttr(credCerBtn, 'aria-disabled', !d.hasCer ? 'true' : null);
      safeAttr(credCerBtn, 'tabindex', !d.hasCer ? '-1' : null);
    }

    if (credKeyBtn) {
      credKeyBtn.href = d.keyUrl || '#';
      credKeyBtn.classList.toggle('is-disabled', !d.hasKey);
      safeAttr(credKeyBtn, 'aria-disabled', !d.hasKey ? 'true' : null);
      safeAttr(credKeyBtn, 'tabindex', !d.hasKey ? '-1' : null);
    }

    if (d.hasPass && d.pass) {
      if (credPassInput) {
        credPassInput.setAttribute('type', 'password');
        credPassInput.value = String(d.pass || '');
      }
      show(credPassUi);
      hide(credPassEmpty);
    } else {
      if (credPassInput) {
        credPassInput.setAttribute('type', 'password');
        credPassInput.value = '';
      }
      hide(credPassUi);
      show(credPassEmpty);
    }

    setText(credIdShort, d.id ? (String(d.id).slice(0, 18) + (String(d.id).length > 18 ? '…' : '')) : '—');
    setText(credIdFull, d.id);

    setText(credCreated, (d.createdAgo && d.createdAgo !== '—') ? d.createdAgo : d.created);
    setText(credUpdated, (d.updatedAgo && d.updatedAgo !== '—') ? d.updatedAgo : d.updated);
    setText(credValidated, d.validated || '—');
  }

  // ===== Delete helpers =====
  function getDeleteTemplate() {
    if (!root) return '';
    return root.getAttribute('data-rt-delete') || '';
  }

  function getCsrf() {
    if (!root) return '';
    return root.getAttribute('data-csrf') || '';
  }

  async function doDelete(id, rfc) {
    const tpl = getDeleteTemplate();
    if (!tpl) {
      toast('Ruta de eliminación no configurada en la vista.', 'error');
      return false;
    }

    let url = tpl;
    if (url.includes('___ID___')) url = url.replace('___ID___', encodeURIComponent(id));
    else url = url.replace(/\/$/, '') + '/' + encodeURIComponent(id); // fallback


    const ok = confirm(
      `¿Eliminar credencial?\n\nRFC: ${rfc || '—'}\nID: ${id}\n\nEsta acción no se puede deshacer.`
    );
    if (!ok) return false;

    try {
      const res = await fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': getCsrf(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!res.ok) {
        let msg = `No se pudo eliminar (HTTP ${res.status}).`;
        try {
          const j = await res.json();
          if (j && (j.message || j.error)) msg = j.message || j.error;
        } catch (_) {}
        toast(msg, 'error');
        return false;
      }

      const row = document.querySelector(`.tr[data-id="${cssEscape(id)}"]`);
      if (row) row.remove();


      if (activeId && activeId === id && isCredOpen()) {
        closeCredDrawer();
      }

      toast('Credencial eliminada.', 'success');
      return true;
    } catch (_) {
      toast('Error de red al eliminar.', 'error');
      return false;
    }
  }

  // ===== Delegated click handler =====
  document.addEventListener('click', function (ev) {
    const t = ev.target;

    // Close meta drawer
    if (t.closest('[data-drawer-close]')) {
      closeMetaDrawer();
      return;
    }

    // Close cred drawer
    if (t.closest(credCloseSel)) {
      closeCredDrawer();
      return;
    }

    // Copy direct
    const copyBtn = t.closest('[data-copy]');
    if (copyBtn) {
      const txt = copyBtn.getAttribute('data-copy') || '';
      const msg = copyBtn.getAttribute('data-toast') || 'Copiado';
      copyText(txt, msg);
      return;
    }

    // Copy from selector
    const copyFromBtn = t.closest('[data-copy-from]');
    if (copyFromBtn) {
      const sel = copyFromBtn.getAttribute('data-copy-from') || '';
      const msg = copyFromBtn.getAttribute('data-toast') || 'Copiado';
      if (!sel) return;

      const src = document.querySelector(sel);
      let val = '';
      if (src) {
        if (src.tagName === 'INPUT' || src.tagName === 'TEXTAREA') val = src.value;
        else val = src.textContent;
      }
      copyText(val || '', msg);
      return;
    }

    // Edit (stub)
    const editBtn = t.closest('[data-cred-edit]');
    if (editBtn) {
      closeAllMenus();
      toast('Editar: pendiente de implementar (definir campos editables OPS).', 'error');
      return;
    }

    // Refresh (stub)
    const refreshBtn = t.closest('[data-cred-refresh]');
    if (refreshBtn) {
      closeAllMenus();
      toast('Actualizar: pendiente de implementar (endpoint PATCH/GET detalle).', 'error');
      return;
    }


    // Open meta drawer
    const metaBtn = t.closest('[data-meta-btn], [data-open-meta]');
    if (metaBtn) {
      const title = metaBtn.getAttribute('data-title') || activeMetaTitle || 'Meta';
      const meta = metaBtn.getAttribute('data-meta') || activeMeta || '{}';
      openMetaDrawer(title, meta);
      closeAllMenus();
      return;
    }

    // Password toggle
    const passTgl = t.closest('[data-cred-pass-toggle]');
    if (passTgl) {
      if (!credPassInput) return;
      const isPwd = credPassInput.getAttribute('type') === 'password';
      credPassInput.setAttribute('type', isPwd ? 'text' : 'password');
      passTgl.textContent = isPwd ? 'Ocultar' : 'Ver';
      return;
    }

    // Kebab open/close
    const kebabBtn = t.closest('[data-menu-btn]');
    if (kebabBtn) {
      const menuWrap = kebabBtn.closest('[data-menu]');
      if (!menuWrap) return;
      toggleMenu(menuWrap);
      return;
    }

    // Delete (kebab)
    const delBtn = t.closest('[data-delete-id]');
    if (delBtn) {
      const id = delBtn.getAttribute('data-delete-id') || '';
      const rfc = delBtn.getAttribute('data-delete-rfc') || '';
      closeAllMenus();
      if (id) doDelete(id, rfc);
      return;
    }

    // Delete (drawer)
    const drawerDelBtn = t.closest('[data-cred-delete]');
    if (drawerDelBtn) {
      closeAllMenus();
      if (activeId) doDelete(activeId, activeRfc);
      else toast('No hay credencial seleccionada.', 'error');
      return;
    }

    // Open cred drawer (botón Ver)
    const openCredBtn = t.closest('[data-open-cred]');
    if (openCredBtn) {
      const row = getRowFromTarget(openCredBtn);
      if (!row) return;
      fillCredDrawerFromRow(row);
      openCredDrawer();
      closeAllMenus();
      return;
    }

    // Click en fila abre drawer (si no es acción)
    const rowClick = t.closest('.tr[data-row], .tr[data-id]');
    if (rowClick) {
      if (
        t.closest('.td-actions') ||
        t.closest('a') ||
        t.closest('button') ||
        t.closest('[data-menu]') ||
        t.closest('[data-open-cred]')
      ) {
        // no-op
      } else {
        fillCredDrawerFromRow(rowClick);
        openCredDrawer();
        closeAllMenus();
        return;
      }
    }

    // Click fuera cierra menús
    if (!t.closest('[data-menu]')) {
      closeAllMenus();
    }
  });

  // Copy meta button
  if (btnCopyMeta) {
    btnCopyMeta.addEventListener('click', function () {
      try {
        const txt = metaPreEl ? metaPreEl.textContent : '';
        copyText(txt || '', 'Meta copiada');
      } catch (_) {}
    });
  }

  // Keyboard shortcuts
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      closeAllMenus();

      if (metaDrawer && metaDrawer.getAttribute('aria-hidden') === 'false') {
        ev.preventDefault();
        closeMetaDrawer();
        return;
      }

      if (credDrawer && credDrawer.getAttribute('aria-hidden') === 'false') {
        ev.preventDefault();
        closeCredDrawer();
        return;
      }
    }

    if ((ev.ctrlKey || ev.metaKey) && (ev.key === 'k' || ev.key === 'K')) {
      if (search) {
        ev.preventDefault();
        try { search.focus(); search.select(); } catch (_) {}
      }
    }
  });

  // Prevent disabled download links
  (function preventDisabledLinks() {
    if (!credDrawer) return;
    credDrawer.addEventListener('click', function (ev) {
      const a = ev.target.closest('a');
      if (!a) return;
      if (a.classList.contains('is-disabled') || a.getAttribute('aria-disabled') === 'true') {
        ev.preventDefault();
        toast('Archivo no disponible para esta credencial.', 'error');
      }
    });
  })();

})();
