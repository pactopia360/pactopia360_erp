(function () {
  'use strict';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  const overlay = $('#mcOverlay');

  function setOverlay(active) {
    if (!overlay) return;
    overlay.classList.toggle('is-on', !!active);
    document.body.classList.toggle('mc-modal-open', !!active);
  }

  function openDialog(dialog) {
    if (!dialog) return;

    try {
      if (typeof dialog.showModal === 'function') {
        if (!dialog.open) dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
    } catch (e) {
      dialog.setAttribute('open', 'open');
    }

    setOverlay(true);
  }

  function closeDialog(dialog) {
    if (!dialog) return;

    try {
      if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
      } else {
        dialog.removeAttribute('open');
      }
    } catch (e) {
      dialog.removeAttribute('open');
    }

    const anyOpen = $$('dialog.mc-modal[open]').length > 0;
    setOverlay(anyOpen);
  }

  function closeAllDialogs() {
    $$('dialog.mc-modal[open]').forEach(closeDialog);
    setOverlay(false);
  }

  function openConfigTab(tab) {
    if (!tab) return;

    const modal = $('#configModal');
    if (!modal) return;

    const tabBtn = $('[data-mc-tab="' + tab + '"]', modal);
    if (tabBtn) tabBtn.click();

    openDialog(modal);
  }

  function openInvoicesModal(button) {
    const modal = $('#invoicesModal');
    const frame = $('#invoicesFrame');
    const url = button ? button.getAttribute('data-invoices-url') : '';

    if (frame && url && url !== '#' && frame.getAttribute('src') !== url) {
      frame.setAttribute('src', url);
    }

    openDialog(modal);
  }

  document.addEventListener('click', function (e) {
    const sectionBtn = e.target.closest('[data-mc-toggle]');
    if (sectionBtn) {
      e.preventDefault();

      const key = sectionBtn.getAttribute('data-mc-toggle');
      const section = document.querySelector('[data-mc-section="' + key + '"]');

      if (!section) return;

      const isOpen = section.getAttribute('data-open') === '1';
      section.setAttribute('data-open', isOpen ? '0' : '1');
      sectionBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      return;
    }

    const tabBtn = e.target.closest('[data-mc-tab]');
    if (tabBtn) {
      e.preventDefault();

      const modal = tabBtn.closest('.mc-modal') || document;
      const tab = tabBtn.getAttribute('data-mc-tab');

      $$('[data-mc-tab]', modal).forEach((btn) => {
        btn.classList.toggle('is-active', btn === tabBtn);
      });

      $$('[data-mc-pane]', modal).forEach((pane) => {
        pane.hidden = pane.getAttribute('data-mc-pane') !== tab;
      });

      return;
    }

    const openConfigWithTab = e.target.closest('[data-mc-open-tab]');
    if (openConfigWithTab) {
      e.preventDefault();
      openConfigTab(openConfigWithTab.getAttribute('data-mc-open-tab'));
      return;
    }

    if (e.target.closest('[data-open-config-modal]')) {
      e.preventDefault();
      openDialog($('#configModal'));
      return;
    }

    if (e.target.closest('[data-close-config-modal]')) {
      e.preventDefault();
      closeDialog($('#configModal'));
      return;
    }

    if (e.target.closest('[data-open-billing-modal]')) {
      e.preventDefault();
      openDialog($('#billingModal'));
      return;
    }

    if (e.target.closest('[data-close-billing-modal]')) {
      e.preventDefault();
      closeDialog($('#billingModal'));
      return;
    }

    if (e.target.closest('[data-open-payments-modal]')) {
      e.preventDefault();
      openDialog($('#paymentsModal'));
      return;
    }

    if (e.target.closest('[data-close-payments-modal]')) {
      e.preventDefault();
      closeDialog($('#paymentsModal'));
      return;
    }

    const invoicesBtn = e.target.closest('[data-open-invoices-modal]');
    if (invoicesBtn) {
      e.preventDefault();
      openInvoicesModal(invoicesBtn);
      return;
    }

    if (e.target.closest('[data-close-invoices-modal]')) {
      e.preventDefault();
      closeDialog($('#invoicesModal'));
      return;
    }

    if (overlay && e.target === overlay) {
      closeAllDialogs();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAllDialogs();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    const root = $('.page-mi-cuenta');
    if (!root) return;

    const modalToOpen = root.getAttribute('data-open-modal-on-load');
    const tabToOpen = root.getAttribute('data-open-config-tab-on-load');

    if (tabToOpen) {
      const tabBtn = $('[data-mc-tab="' + tabToOpen + '"]');
      if (tabBtn) tabBtn.click();
    }

    if (modalToOpen === 'billing') openDialog($('#billingModal'));
    if (modalToOpen === 'config') openDialog($('#configModal'));
    if (modalToOpen === 'payments') openDialog($('#paymentsModal'));

    if (modalToOpen === 'invoices') {
      openInvoicesModal($('[data-open-invoices-modal]'));
    }
  });
})();