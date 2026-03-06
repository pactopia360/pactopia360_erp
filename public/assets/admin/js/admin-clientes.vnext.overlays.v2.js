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

  installGlobalEscClose();
  observeOverlayState();
  installAdvDetailsScrollIntoView();

})();