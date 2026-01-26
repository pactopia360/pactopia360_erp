// public/assets/client/js/sat/sat-external-invite.js
// Registro Externo: enviar liga + cerrar con ESC

(() => {
  'use strict';

  const APP = window.P360_SAT_APP || {};
  const ROUTES = APP.ROUTES || {};
  const CSRF   = APP.CSRF || '';
  const satToast = APP.satToast || function(){};

  const safeJson = APP.safeJson || (async () => ({}));
  const mkHttpError = APP.mkHttpError || ((_, __, m) => new Error(m || 'Error'));

  // ============================================================
  // REGISTRO EXTERNO – enviar liga
  // ============================================================
  (function initExternalInvite() {
    const modal = document.getElementById('modalExternalRfcInvite');
    if (!modal) return;

    const btnSend =
      document.getElementById('btnExternalInviteSend') ||
      modal.querySelector('[data-external-invite-send]') ||
      modal.querySelector('button[type="submit"]');

    const elEmail = document.getElementById('externalInviteEmail') || modal.querySelector('#externalInviteEmail');
    const elNote  = document.getElementById('externalInviteNote')  || modal.querySelector('#externalInviteNote');

    async function postExternalInvite(url, payload) {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload || {}),
      });

      const data = await safeJson(res);

      if (data && data._non_json) throw mkHttpError(res, data, 'Respuesta no JSON al enviar invitación.');
      if (!res.ok || data.ok === false) throw mkHttpError(res, data, 'No se pudo enviar la liga de registro externo.');

      return data;
    }

    function close() { modal.style.display = 'none'; }

    async function sendInvite(ev) {
      if (ev) ev.preventDefault();

      const url = String(ROUTES.externalInvite || '').trim();
      if (!url) {
        satToast('Ruta no configurada: P360_SAT.routes.externalInvite (backend pendiente).', 'error');
        console.error('[SAT-EXT] Missing ROUTES.externalInvite.');
        return;
      }

      const email = String(elEmail?.value || '').trim();
      const note  = String(elNote?.value  || '').trim();

      if (!email) return satToast('Escribe un correo válido.', 'error');
      if (!email.includes('@') || !email.includes('.')) return satToast('Correo inválido.', 'error');

      if (btnSend) {
        btnSend.disabled = true;
        btnSend.classList.add('is-loading');
      }

      try {
        const data = await postExternalInvite(url, { email, note: note || null });

        const out = modal.querySelector('[data-external-invite-result], #externalInviteResult');
        if (out) out.textContent = data.msg || 'Liga enviada correctamente.';

        satToast(data.msg || 'Liga enviada. Revisa el correo del emisor.', 'ok');
        close();
      } catch (e) {
        console.error('[SAT-EXT] sendInvite error', e);
        satToast(e.message || 'No se pudo enviar la liga.', 'error');
      } finally {
        if (btnSend) {
          btnSend.disabled = false;
          btnSend.classList.remove('is-loading');
        }
      }
    }

    if (btnSend) btnSend.addEventListener('click', sendInvite);

    const form = modal.querySelector('form');
    if (form) form.addEventListener('submit', sendInvite);
  })();

  // ============================================================
  // Cerrar modal externo con ESC
  // ============================================================
  (function bindExternalInviteEsc() {
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      const modal = document.getElementById('modalExternalRfcInvite');
      if (!modal) return;

      const isOpen = (modal.style.display && modal.style.display !== 'none');
      if (!isOpen) return;

      modal.style.display = 'none';
    });
  })();
})();
