// public/assets/client/js/sat/sat-forms-modals.js
// Toggles + verify + request form + modal RFC + dropdown RFC + modal Auto

(() => {
  'use strict';

  const APP    = window.P360_SAT_APP || {};
  const ROUTES = APP.ROUTES || {};
  const CSRF   = APP.CSRF || '';
  const IS_PRO = !!APP.IS_PRO;

  const satToast = APP.satToast || function(){};
  const safeJson = APP.safeJson || (async () => ({}));

  // ============================================================
  // Cambiar modo DEMO / PROD
  // ============================================================
  (function initModeToggle() {
    const badge = document.getElementById('badgeMode');
    if (!badge || !ROUTES.mode) return;

    let busy = false;

    badge.addEventListener('click', async () => {
      if (busy) return;
      busy = true;
      try {
        const res = await fetch(ROUTES.mode, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
          },
        });
        const j = await safeJson(res);
        if (j && j.mode) location.reload();
        else satToast('No se pudo cambiar el modo.', 'error');
      } catch (e) {
        console.error(e);
        satToast('No se pudo cambiar el modo.', 'error');
      } finally {
        busy = false;
      }
    });
  })();

  // ============================================================
  // Verificar solicitudes (POST)
  // ============================================================
  (function initVerifyButton() {
    const btn = document.getElementById('btnSatVerify');
    if (!btn || !ROUTES.verify) return;

    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try {
        const r = await fetch(ROUTES.verify, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
          },
        });

        const j = await safeJson(r);

        if (j && j._non_json) {
          satToast('Respuesta no JSON al verificar. Revisa sesión/CSRF.', 'error');
          console.error('[SAT-VERIFY] non-json response', { status: r.status, text: j._text });
          return;
        }

        satToast(`Pendientes: ${j.pending ?? 0} · Listos: ${j.ready ?? 0}`, 'ok');
        location.reload();
      } catch (e) {
        console.error(e);
        satToast('No se pudo verificar', 'error');
      } finally {
        btn.disabled = false;
      }
    });
  })();

  // ============================================================
  // Envío de solicitudes SAT (regla FREE 1 mes)
  // ============================================================
  (function initRequestForm() {
    const f = document.getElementById('reqForm');
    if (!f) return;

    f.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      const fd = new FormData(f);
      const fromVal = fd.get('from');
      const toVal = fd.get('to');

      if (!IS_PRO) {
        const a = new Date(fromVal);
        const b = new Date(toVal);
        if (!isNaN(+a) && !isNaN(+b)) {
          const diffMs = b - a;
          const maxMs = 32 * 24 * 3600 * 1000;
          if (diffMs > maxMs) {
            satToast('En FREE sólo puedes solicitar hasta 1 mes por ejecución.', 'error');
            return;
          }
        }
      }

      const submitBtn = f.querySelector('.sat-req-submit');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
      }

      try {
        const res = await fetch(f.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
          },
          body: fd,
        });

        const data = await safeJson(res);

        if (data && data._non_json) {
          satToast('Respuesta no JSON al crear solicitud. Revisa sesión/CSRF.', 'error');
          console.error('[SAT-REQUEST] non-json response', { status: res.status, text: data._text });
          return;
        }

        if (!res.ok || !data || data.ok === false) {
          satToast(data?.msg || data?.message || 'No se pudo crear la solicitud SAT.', 'error');
          return;
        }

        location.reload();
      } catch (e) {
        console.error(e);
        satToast('Error de conexión al enviar la solicitud.', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Solicitar';
        }
      }
    });
  })();

  // ============================================================
  // Modal RFC (abrir / cerrar / guardar)
  // ============================================================
  (function initRfcModal() {
    const modal = document.getElementById('modalRfc');
    const form  = document.getElementById('formRfc');
    if (!modal || !form) return;

    function open() {
      try { modal.style.display = 'flex'; } catch (_) {}
      modal.classList.add('is-open');
    }

    function close() {
      modal.classList.remove('is-open');
      try { modal.style.display = 'none'; } catch (_) {}
    }

    function fileSelected(input) {
      try {
        if (!input) return false;
        if (!input.files || !input.files.length) return false;
        return !!(input.files[0] && input.files[0].name);
      } catch (e) {
        return false;
      }
    }

    function getFileInputs() {
      // Soporta nombres alternos por si tu Blade no usa exactamente cer/key
      const cer = form.querySelector('input[type="file"][name="cer"], input[type="file"][name="cer_file"], input[type="file"][name="csd_cer"]');
      const key = form.querySelector('input[type="file"][name="key"], input[type="file"][name="key_file"], input[type="file"][name="csd_key"]');
      return { cer, key };
    }

    function getPasswordValue(fd) {
      const v =
        fd.get('key_password') ??
        fd.get('csd_password') ??
        fd.get('password') ??
        '';
      return String(v || '').trim();
    }

    // Triggers flexibles (tu UI usa data-open="modal-rfc" y/o #btnAddRfc)
    document.addEventListener('click', (ev) => {
      const openBtn =
        ev.target.closest('[data-open="add-rfc"]') ||
        ev.target.closest('[data-open="modal-rfc"]') ||
        ev.target.closest('[data-open="modalRfc"]') ||
        ev.target.closest('#btnAddRfc');

      const closeBtn =
        ev.target.closest('[data-close="modal-rfc"]') ||
        ev.target.closest('[data-close="modalRfc"]');

      if (openBtn) { ev.preventDefault(); open(); }
      if (closeBtn || ev.target === modal) { ev.preventDefault(); close(); }
    }, true);

    // Compat con disparo por evento custom
    window.addEventListener('sat-open-add-rfc', open);

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      const fd = new FormData(form);

      const { cer, key } = getFileInputs();
      const hasCer = fileSelected(cer) || (fd.get('cer') instanceof File && fd.get('cer')?.name);
      const hasKey = fileSelected(key) || (fd.get('key') instanceof File && fd.get('key')?.name);
      const pwd    = getPasswordValue(fd);

      // -----------------------------
      // FIX: evita ir a csdStore si solo hay password (sin archivos)
      // -----------------------------
      if (pwd && !hasCer && !hasKey) {
        satToast('Para registrar CSD debes adjuntar .cer y .key (la contraseña sola no basta).', 'error');
        if (submitBtn) submitBtn.disabled = false;
        return;
      }

      // Si adjunta 1 archivo, debe adjuntar ambos
      if ((hasCer && !hasKey) || (!hasCer && hasKey)) {
        satToast('Adjunta ambos archivos: .cer y .key.', 'error');
        if (submitBtn) submitBtn.disabled = false;
        return;
      }

      // Si hay archivos (cer+key) => csdStore; si no, solo RFC => rfcReg
      const useCsd = (hasCer && hasKey);

      const url = useCsd ? ROUTES.csdStore : ROUTES.rfcReg;

      if (!url) {
        satToast('Ruta de guardado de RFC/CSD no configurada.', 'error');
        if (submitBtn) submitBtn.disabled = false;
        return;
      }

      try {
        const res = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            // NO fijar Content-Type en multipart (FormData)
          },
          body: fd,
        });

        const data = await safeJson(res);

        if (data && data._non_json) {
          satToast('Respuesta no JSON al guardar RFC/CSD. Revisa sesión/CSRF.', 'error');
          console.error('[SAT-RFC] non-json response', { status: res.status, text: data._text });
          return;
        }

        if (!res.ok || (data && data.ok === false)) {
          satToast(data?.msg || data?.message || 'No se pudo guardar el RFC / CSD.', 'error');
          return;
        }

        close();
        if (data && data.redirect) window.location.href = data.redirect;
        else location.reload();
      } catch (e) {
        console.error(e);
        satToast('Error de conexión al enviar los datos.', 'error');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  })();

  // ============================================================
  // Dropdown RFCs con checkboxes
  // ============================================================
  document.addEventListener('DOMContentLoaded', () => {
    const dd = document.getElementById('satRfcDropdown');
    const hidden = document.getElementById('satRfcs');
    if (!dd || !hidden) return;

    const trigger = dd.querySelector('#satRfcTrigger');
    const menu = dd.querySelector('#satRfcMenu');
    const allCb = dd.querySelector('#satRfcAll');
    const itemCbs = Array.from(dd.querySelectorAll('.satRfcItem'));
    const summary = dd.querySelector('#satRfcSummary');

    function updateHiddenAndSummary() {
      const selected = itemCbs.filter(cb => cb.checked).map(cb => cb.value);

      hidden.innerHTML = '';
      selected.forEach(val => {
        const opt = document.createElement('option');
        opt.value = val;
        opt.selected = true;
        hidden.appendChild(opt);
      });

      if (!summary) return;

      if (selected.length === 0) summary.textContent = '(Ningún RFC seleccionado)';
      else if (selected.length === itemCbs.length) { summary.textContent = '(Todos los RFCs)'; if (allCb) allCb.checked = true; }
      else if (selected.length === 1) {
        const cb = itemCbs.find(c => c.value === selected[0]);
        summary.textContent = cb ? cb.parentElement.textContent.trim() : '(1 RFC seleccionado)';
        if (allCb) allCb.checked = false;
      } else {
        summary.textContent = selected.length + ' RFCs seleccionados';
        if (allCb) allCb.checked = false;
      }
    }

    if (trigger && menu) {
      trigger.addEventListener('click', () => menu.classList.toggle('is-open'));
      document.addEventListener('click', (e) => { if (!dd.contains(e.target)) menu.classList.remove('is-open'); });
    }

    if (allCb) {
      allCb.addEventListener('change', () => {
        const checked = allCb.checked;
        itemCbs.forEach(cb => (cb.checked = checked));
        updateHiddenAndSummary();
      });
    }

    itemCbs.forEach(cb => {
      cb.addEventListener('change', () => {
        if (allCb) allCb.checked = itemCbs.every(c => c.checked);
        updateHiddenAndSummary();
      });
    });

    if (allCb) allCb.checked = true;
    itemCbs.forEach(cb => (cb.checked = true));
    updateHiddenAndSummary();
  });

  // ============================================================
  // Modal Automatizar descargas
  // ============================================================
  (function initAutoModal() {
    const modal = document.getElementById('modalAuto');
    if (!modal) return;

    const closeBtn = modal.querySelector('[data-close="modal-auto"]');
    const open  = () => {
      try { modal.style.display = 'flex'; } catch (_) {}
      modal.classList.add('is-open');
    };
    const close = () => {
      modal.classList.remove('is-open');
      try { modal.style.display = 'none'; } catch (_) {}
    };

    document.addEventListener('click', (e) => {
      const openBtn = e.target.closest('.auto-modal-btn');
      if (openBtn) { e.preventDefault(); open(); }
    }, true);

    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => { e.preventDefault(); close(); });
    }

    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
  })();

})();
