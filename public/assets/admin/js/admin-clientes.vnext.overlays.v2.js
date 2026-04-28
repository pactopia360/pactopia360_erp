// public/assets/admin/js/admin-clientes.vnext.overlays.v2.js
// Pactopia360 · Admin Clientes · Overlays v2 (Drawer + Modals)
// NO rompe legacy: solo añade mejoras UX y normaliza scroll/escape.

(function () {
  'use strict';

  const $ = (s, sc) => (sc || document).querySelector(s);

  function isShown(el){
    if (!el) return false;
    return el.classList.contains('show') || el.getAttribute('aria-hidden') === 'false';
  }

  function closeTopmostOverlay() {
    // Prioridad: iframe modal > cualquier modal > drawer
    const ifm = $('#acIframeModal');
    if (ifm && isShown(ifm)) {
      const btn = ifm.querySelector('[data-close-ac-iframe]');
      btn && btn.click();
      return true;
    }

    const modal = Array.from(document.querySelectorAll('.ac-modal.show')).at(-1);
    if (modal) {
      const btn = modal.querySelector('[data-close-modal]');
      btn && btn.click();
      return true;
    }

    const drawer = $('#clientDrawer');
    if (drawer && drawer.getAttribute('aria-hidden') === 'false') {
      const btn = drawer.querySelector('[data-close-drawer]');
      btn && btn.click();
      return true;
    }

    return false;
  }

  function normalizeBodyScrollLock() {
    // Si hay modal o drawer abiertos, lock al body (pero deja scroll interno del overlay)
    const hasModal = document.querySelector('.ac-modal.show');
    const drawer = $('#clientDrawer');
    const hasDrawer = drawer && drawer.getAttribute('aria-hidden') === 'false';

    const lock = !!(hasModal || hasDrawer);

    document.documentElement.classList.toggle('ac-modal-open', lock);
    document.body.classList.toggle('ac-modal-open', lock);
  }

  function installGlobalEscClose() {
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (closeTopmostOverlay()) {
        e.preventDefault();
      }
    });
  }

  function observeOverlayState() {
    const targets = [
      ...document.querySelectorAll('.ac-modal'),
      $('#clientDrawer')
    ].filter(Boolean);

    const obs = new MutationObserver(() => {
      normalizeBodyScrollLock();
    });

    targets.forEach(t => obs.observe(t, { attributes: true, attributeFilter: ['class', 'aria-hidden'] }));

    // initial
    normalizeBodyScrollLock();
  }

  // Opcional: mejora pequeña de “Details” avanzadas (si se abre, scroll into view)
  function installAdvDetailsScrollIntoView() {
    document.addEventListener('toggle', function(e){
      const d = e.target;
      if (!d || !d.classList || !d.classList.contains('ac-adv')) return;
      if (d.open) {
        setTimeout(() => {
          try { d.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch(_){}
        }, 40);
      }
    }, true);
  }

    function installRegisteredAtEditFill() {
    document.addEventListener('click', function (e) {
      const editBtn = e.target.closest('[data-drawer-action="edit"], #btnOpenEdit');
      if (!editBtn) return;

      setTimeout(function () {
        const modal = document.getElementById('modalEdit');
        const input = document.getElementById('mEdit_registered_at');

        if (!modal || !input) return;

        let data = {};

        const drawer = document.getElementById('clientDrawer');
        const activeRow = document.querySelector('.ac-row.is-active, .ac-row.ac-active, .ac-row[data-current="1"]');

        let row = editBtn.closest('.ac-row') || activeRow;

        if (!row && drawer) {
          const currentId = (document.getElementById('mEdit_id')?.value || '').trim();

          if (currentId !== '') {
            row = Array.from(document.querySelectorAll('.ac-row[data-client]')).find(function (candidate) {
              try {
                const payload = JSON.parse(candidate.getAttribute('data-client') || '{}');
                return String(payload.id || payload.key || '') === currentId;
              } catch (_) {
                return false;
              }
            });
          }
        }

        if (row) {
          try {
            data = JSON.parse(row.getAttribute('data-client') || '{}');
          } catch (_) {
            data = {};
          }
        }

        if (data.registered_at) {
          input.value = String(data.registered_at).slice(0, 10);
          return;
        }

        if (!input.value) {
          const createdText = (data.created || '').trim();
          const match = createdText.match(/^(\d{4}-\d{2}-\d{2})/);

          if (match) {
            input.value = match[1];
          }
        }
      }, 120);
    });
  }

  installRegisteredAtEditFill();

  installGlobalEscClose();
  observeOverlayState();
  installAdvDetailsScrollIntoView();

})();