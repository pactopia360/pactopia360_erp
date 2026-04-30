(function () {
  'use strict';

  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

  function openDialog(id) {
    const dialog = document.getElementById(id);
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

    document.body.classList.add('rh360-modal-open');
    updateAi(dialog);

    const firstInput = dialog.querySelector('input:not([type="hidden"]), select, textarea, button');
    if (firstInput) {
      setTimeout(() => firstInput.focus({ preventScroll: true }), 80);
    }
  }

  function closeDialog(dialogOrButton) {
    const dialog = dialogOrButton instanceof HTMLDialogElement
      ? dialogOrButton
      : dialogOrButton.closest('dialog');

    if (!dialog) return;

    try {
      if (typeof dialog.close === 'function') {
        dialog.close();
      } else {
        dialog.removeAttribute('open');
      }
    } catch (e) {
      dialog.removeAttribute('open');
    }

    document.body.classList.remove('rh360-modal-open');
  }

  function val(dialog, name) {
    const el = dialog.querySelector('[data-rh-ai="' + name + '"]');
    return el ? String(el.value || '').trim() : '';
  }

  function updateAi(dialog) {
    const list = dialog.querySelector('[data-rh-ai-list]');
    const scoreEl = dialog.querySelector('[data-rh-ai-score]');
    const levelEl = dialog.querySelector('[data-rh-ai-level]');

    if (!list || !scoreEl || !levelEl) return;

    const checks = [
      {
        ok: /^[A-ZÑ&0-9]{12,13}$/i.test(val(dialog, 'rfc')),
        text: 'RFC obligatorio: 12 o 13 caracteres válidos.'
      },
      {
        ok: /^[A-Z0-9]{18}$/i.test(val(dialog, 'curp')),
        text: 'CURP obligatorio para receptor de nómina.'
      },
      {
        ok: /^\d{5}$/.test(val(dialog, 'codigo_postal')),
        text: 'CP fiscal obligatorio CFDI 4.0: 5 dígitos.'
      },
      {
        ok: val(dialog, 'regimen_fiscal') === '605',
        text: 'Régimen fiscal receptor debe ser 605 para nómina.'
      },
      {
        ok: val(dialog, 'uso_cfdi').toUpperCase() === 'CN01',
        text: 'Uso CFDI debe ser CN01 en CFDI de nómina.'
      },
      {
        ok: val(dialog, 'numero_empleado') !== '',
        text: 'Número de empleado obligatorio en complemento nómina.'
      },
      {
        ok: val(dialog, 'nombre') !== '',
        text: 'Nombre del empleado obligatorio.'
      },
      {
        ok: val(dialog, 'apellido_paterno') !== '',
        text: 'Apellido paterno recomendado para nombre fiscal completo.'
      },
      {
        ok: val(dialog, 'tipo_contrato') !== '',
        text: 'Tipo contrato obligatorio en complemento nómina.'
      },
      {
        ok: val(dialog, 'tipo_regimen') !== '',
        text: 'Tipo régimen obligatorio en complemento nómina.'
      },
      {
        ok: val(dialog, 'periodicidad_pago') !== '',
        text: 'Periodicidad de pago obligatoria.'
      }
    ];

    const okCount = checks.filter(item => item.ok).length;
    const score = Math.round((okCount / checks.length) * 100);

    scoreEl.textContent = score + '%';

    if (score >= 90) {
      levelEl.textContent = 'Listo para CFDI Nómina';
    } else if (score >= 70) {
      levelEl.textContent = 'Buen avance, faltan detalles';
    } else {
      levelEl.textContent = 'Completa datos críticos';
    }

    list.innerHTML = checks.map(item => {
      return '<li class="' + (item.ok ? 'is-ok' : 'is-pending') + '">' + item.text + '</li>';
    }).join('');
  }

  document.addEventListener('click', function (event) {
    const openBtn = event.target.closest('[data-open-dialog]');
    if (openBtn) {
      event.preventDefault();
      event.stopPropagation();
      openDialog(openBtn.dataset.openDialog);
      return;
    }

    const closeBtn = event.target.closest('[data-close-dialog]');
    if (closeBtn) {
      event.preventDefault();
      event.stopPropagation();
      closeDialog(closeBtn);
      return;
    }

    const dialog = event.target.closest('.rh360-dialog');
    if (dialog && event.target === dialog) {
      closeDialog(dialog);
    }
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;

    const opened = document.querySelector('.rh360-dialog[open]');
    if (opened) closeDialog(opened);
  });

  $$('.rh360-dialog').forEach(function (dialog) {
    dialog.addEventListener('close', function () {
      if (!document.querySelector('.rh360-dialog[open]')) {
        document.body.classList.remove('rh360-modal-open');
      }
    });

    $$('input, select, textarea', dialog).forEach(function (field) {
      field.addEventListener('input', function () {
        updateAi(dialog);
      });

      field.addEventListener('change', function () {
        updateAi(dialog);
      });
    });

    const form = $('form', dialog);
    if (form) {
      form.addEventListener('submit', function () {
        const submitBtn = form.querySelector('[type="submit"]');
        if (!submitBtn) return;

        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');

        const originalText = submitBtn.dataset.originalText || submitBtn.textContent.trim();
        submitBtn.dataset.originalText = originalText;
        submitBtn.textContent = 'Guardando...';
      });
    }

    updateAi(dialog);
  });
})();