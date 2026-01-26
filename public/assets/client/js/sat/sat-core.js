// public/assets/client/js/sat/sat-core.js
// SAT · Core helpers + cfg + toast + safe fetch utils
// Sin imports, usa namespace global window.P360_SAT_APP

(() => {
  'use strict';

  window.P360_SAT = window.P360_SAT || {};

  const cfg    = window.P360_SAT || {};
  const ROUTES = cfg.routes || {};
  const CSRF   = cfg.csrf || '';
  const IS_PRO = !!cfg.isProPlan;
  const VAULT  = cfg.vault || {};

  // Namespace compartido entre módulos
  window.P360_SAT_APP = window.P360_SAT_APP || {};
  const APP = window.P360_SAT_APP;

  APP.cfg    = cfg;
  APP.ROUTES = ROUTES;
  APP.CSRF   = CSRF;
  APP.IS_PRO = IS_PRO;
  APP.VAULT  = VAULT;

  // ============================================================
  // Hook global: abrir modal "Registro externo" desde cualquier módulo
  // ============================================================
  window.P360_SAT.openExternalRfcInvite = function () {
    try {
      const modal = document.getElementById('modalExternalRfcInvite');
      if (!modal) return false;
      modal.style.display = 'flex';
      const email = document.getElementById('externalInviteEmail');
      if (email) email.focus();
      return true;
    } catch (e) {
      console.error('[SAT] openExternalRfcInvite error', e);
      return false;
    }
  };

  // ============================================================
  // Toast SAT (notificaciones ligeras)
  // ============================================================
  function satToast(msg, kind = 'info') {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) window.P360.toast.error(msg);
        else window.P360.toast(msg);
        return;
      }
    } catch (_) {}

    let host = document.getElementById('satToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'satToastHost';
      host.className = 'sat-toast-host';
      document.body.appendChild(host);
    }

    const el = document.createElement('div');
    el.className = 'sat-toast sat-toast-' + (kind === 'error' ? 'error' : 'ok');
    el.textContent = msg || 'OK';
    host.appendChild(el);

    requestAnimationFrame(() => el.classList.add('is-visible'));

    setTimeout(() => {
      el.classList.remove('is-visible');
      setTimeout(() => el.remove(), 260);
    }, 2600);
  }

  APP.satToast = satToast;

  // Parche: reemplaza alert() por toast cuando sea mensaje conocido
  (function patchAlert() {
    const nativeAlert = window.alert;
    window.alert = function (msg) {
      if (msg === 'Carrito actualizado.' || msg === 'Carrito actualizado') {
        satToast(msg, 'ok'); return;
      }
      if (msg === 'Descargas agregadas al carrito.' || msg === 'Descargas agregadas al carrito') {
        satToast(msg, 'ok'); return;
      }
      if (typeof msg === 'string' && msg.includes('Falta configurar la ruta del envío')) {
        satToast(msg, 'error'); return;
      }
      return nativeAlert(msg);
    };
  })();

  // ============================================================
  // Helpers
  // ============================================================
  function parseIsoDate(str) {
    if (!str) return null;
    const d = new Date(str);
    return isNaN(d.getTime()) ? null : d;
  }

  function formatCountdown(msDiff) {
    if (msDiff <= 0) return '00:00:00';
    const totalSec = Math.floor(msDiff / 1000);
    const h = String(Math.floor(totalSec / 3600)).padStart(2, '0');
    const m = String(Math.floor((totalSec % 3600) / 60)).padStart(2, '0');
    const s = String(totalSec % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
  }

  function isJsonResponse(res) {
    const ct = (res?.headers?.get('content-type') || '').toLowerCase();
    return ct.includes('application/json') || ct.includes('text/json') || ct.includes('+json');
  }

  async function safeText(res, maxLen = 1200) {
    if (!res) return '';
    try {
      const t = await res.text();
      if (!t) return '';
      return t.length > maxLen ? (t.slice(0, maxLen) + '…') : t;
    } catch (_) {
      return '';
    }
  }

  async function safeJson(res) {
    if (!res) return {};
    if (!isJsonResponse(res)) {
      return { _non_json: true, _text: await safeText(res) };
    }
    try {
      const j = await res.json();
      return (j && typeof j === 'object') ? j : {};
    } catch (_) {
      return { _non_json: true, _text: await safeText(res) };
    }
  }

  function mkHttpError(res, data, fallbackMsg) {
    const msg =
      (data && (data.msg || data.message)) ||
      (data && data._non_json && data._text ? 'Respuesta no JSON: ' + data._text : '') ||
      fallbackMsg ||
      'Error en la operación.';
    const err = new Error(msg);
    err.status = res?.status;
    err.data = data;
    return err;
  }

  APP.parseIsoDate = parseIsoDate;
  APP.formatCountdown = formatCountdown;
  APP.isJsonResponse = isJsonResponse;
  APP.safeText = safeText;
  APP.safeJson = safeJson;
  APP.mkHttpError = mkHttpError;

  // ============================================================
  // Bóveda: helpers compartidos
  // ============================================================
  function buildVaultFromDownloadUrl(downloadId) {
    const tpl = ROUTES.vaultFromDownload || '';
    if (!tpl) return '';
    return String(tpl).replace('__ID__', encodeURIComponent(String(downloadId)));
  }

  async function vaultStoreFromDownload(downloadId) {
    const url = buildVaultFromDownloadUrl(downloadId);
    if (!url) throw new Error('Ruta vaultFromDownload no configurada en window.P360_SAT.routes.');

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    });

    const data = await safeJson(res);

    if (data && data._non_json) throw mkHttpError(res, data, 'Respuesta no JSON al guardar en Bóveda.');
    if (!res.ok || data.ok === false) throw mkHttpError(res, data, 'No se pudo guardar en Bóveda.');

    return data;
  }

  APP.buildVaultFromDownloadUrl = buildVaultFromDownloadUrl;
  APP.vaultStoreFromDownload = vaultStoreFromDownload;

  // Debug
  // console.log('[SAT-CORE] init', { routes: ROUTES, isPro: IS_PRO, vault: VAULT });
})();
