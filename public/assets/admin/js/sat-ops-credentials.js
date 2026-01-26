// public/assets/admin/js/sat-ops-credentials.js
// P360 · Admin · SAT Ops · Credentials (UI actions)
// - Copy buttons (data-copy)
// - Password toggle (data-pass-toggle)
// - Meta drawer (data-meta-btn)
// - Kebab menu (data-menu)
// - Delete (data-delete-id) via fetch DELETE if route exists in #p360OpsCred[data-rt-delete]
// - Ctrl+K focuses search

(function () {
  'use strict';

  if (window.__P360_SAT_OPS_CRED_JS__) return;
  window.__P360_SAT_OPS_CRED_JS__ = true;

  const root = document.getElementById('p360OpsCred');
  const search = document.getElementById('opsSearch');

  const drawer = document.getElementById('metaDrawer');
  const titleEl = document.getElementById('metaTitle');
  const preEl = document.getElementById('metaPre');
  const btnCopyMeta = drawer ? drawer.querySelector('[data-copy-meta]') : null;

  function toast(msg, kind) {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch (e) {}
    // fallback
    try { console.log('[P360]', msg); } catch (e) {}
    alert(msg);
  }

  async function copyText(text, okMsg) {
    try {
      await navigator.clipboard.writeText(String(text || ''));
      if (okMsg) toast(okMsg, 'success');
      return true;
    } catch (e) {
      toast('No se pudo copiar al portapapeles.', 'error');
      return false;
    }
  }

  function openDrawer(t, text) {
    if (!drawer) return;
    try {
      if (titleEl) titleEl.textContent = t || 'Meta';
      if (preEl) preEl.textContent = text || '{}';
      drawer.setAttribute('aria-hidden', 'false');
      document.documentElement.style.overflow = 'hidden';
    } catch (e) {}
  }

  function closeDrawer() {
    if (!drawer) return;
    try {
      drawer.setAttribute('aria-hidden', 'true');
      document.documentElement.style.overflow = '';
    } catch (e) {}
  }

  // ===== Kebab menu helpers =====
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

  // ===== Delete helper =====
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
      return;
    }
    const url = tpl.replace('___ID___', encodeURIComponent(id));

    const ok = confirm(`¿Eliminar credencial?\n\nRFC: ${rfc || '—'}\nID: ${id}\n\nEsta acción no se puede deshacer.`);
    if (!ok) return;

    try {
      const res = await fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': getCsrf(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      // Laravel puede responder 204/200; si viene JSON, lo intentamos leer
      if (!res.ok) {
        let msg = `No se pudo eliminar (HTTP ${res.status}).`;
        try {
          const j = await res.json();
          if (j && (j.message || j.error)) msg = j.message || j.error;
        } catch (e) {}
        toast(msg, 'error');
        return;
      }

      // UI: remover row
      const row = document.querySelector(`.tr[data-id="${CSS.escape(id)}"]`);
      if (row) row.remove();

      toast('Credencial eliminada.', 'success');
    } catch (e) {
      toast('Error de red al eliminar.', 'error');
    }
  }

  // ===== Delegated click handler =====
  document.addEventListener('click', function (ev) {
    // Close drawer
    if (ev.target.closest('[data-drawer-close]')) {
      closeDrawer();
      return;
    }

    // Copy buttons
    const copyBtn = ev.target.closest('[data-copy]');
    if (copyBtn) {
      const txt = copyBtn.getAttribute('data-copy') || '';
      const msg = copyBtn.getAttribute('data-toast') || 'Copiado';
      copyText(txt, msg);
      return;
    }

    // Meta drawer open
    const metaBtn = ev.target.closest('[data-meta-btn]');
    if (metaBtn) {
      const t = metaBtn.getAttribute('data-title') || 'Meta';
      const m = metaBtn.getAttribute('data-meta') || '{}';
      openDrawer(t, m);
      closeAllMenus(); // por si estaba abierto el kebab
      return;
    }

    // Password toggle
    const tgl = ev.target.closest('[data-pass-toggle]');
    if (tgl) {
      const box = tgl.closest('.passrow');
      if (!box) return;
      const inp = box.querySelector('.passinp');
      if (!inp) return;
      const isPwd = inp.getAttribute('type') === 'password';
      inp.setAttribute('type', isPwd ? 'text' : 'password');
      tgl.textContent = isPwd ? 'Ocultar' : 'Ver';
      return;
    }

    // Kebab open/close
    const kebabBtn = ev.target.closest('[data-menu-btn]');
    if (kebabBtn) {
      const menuWrap = kebabBtn.closest('[data-menu]');
      if (!menuWrap) return;
      toggleMenu(menuWrap);
      return;
    }

    // Delete
    const delBtn = ev.target.closest('[data-delete-id]');
    if (delBtn) {
      const id = delBtn.getAttribute('data-delete-id') || '';
      const rfc = delBtn.getAttribute('data-delete-rfc') || '';
      closeAllMenus();
      if (id) doDelete(id, rfc);
      return;
    }

    // Click outside menus closes menus
    if (!ev.target.closest('[data-menu]')) {
      closeAllMenus();
    }
  });

  // Copy meta
  if (btnCopyMeta) {
    btnCopyMeta.addEventListener('click', function () {
      try {
        const txt = preEl ? preEl.textContent : '';
        copyText(txt || '', 'Meta copiada');
      } catch (e) {}
    });
  }

  // ESC closes drawer/menus
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      closeAllMenus();
      if (drawer && drawer.getAttribute('aria-hidden') === 'false') {
        ev.preventDefault();
        closeDrawer();
      }
    }

    // Ctrl+K focus search
    if ((ev.ctrlKey || ev.metaKey) && (ev.key === 'k' || ev.key === 'K')) {
      if (search) {
        ev.preventDefault();
        try { search.focus(); search.select(); } catch (e) {}
      }
    }
  });
})();
