// public/assets/client/js/sat-dashboard.js
// PACTOPIA360 · SAT · Dashboard (cliente) · JS sobre HTML renderizado en Blade
// Versión corregida: cancelDownload estable, bulk-add real, sin referencias globales rotas, sin awaits inútiles.

(() => {
  'use strict';

  const cfg    = window.P360_SAT || {};
  const ROUTES = cfg.routes || {};
  const CSRF   = cfg.csrf || '';
  const IS_PRO = !!cfg.isProPlan;
  const VAULT  = cfg.vault || {};

  console.log('[SAT-DASH] init', { routes: ROUTES, isPro: IS_PRO, vault: VAULT });

  // ============================================================
  // Toast SAT (notificaciones ligeras)
  // ============================================================
  function satToast(msg, kind = 'info') {
    // Si existe P360.toast lo usamos
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) {
          window.P360.toast.error(msg);
        } else {
          window.P360.toast(msg);
        }
        return;
      }
    } catch (_) {}

    // Fallback: toast mínimo
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

  // Parche: por si algún alert('Carrito actualizado.') se cuela
  (function patchAlert() {
    const nativeAlert = window.alert;
    window.alert = function (msg) {
      if (msg === 'Carrito actualizado.' || msg === 'Carrito actualizado') {
        satToast(msg, 'ok');
        return;
      }
      if (msg === 'Descargas agregadas al carrito.' || msg === 'Descargas agregadas al carrito') {
        satToast(msg, 'ok');
        return;
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

  // ============================================================
  // BÓVEDA: guardar ZIP desde una descarga
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
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      throw new Error(data.msg || data.message || 'No se pudo guardar en Bóveda.');
    }
    return data;
  }

  // ============================================================
  // CARRITO SAT – estado global
  // ============================================================
  const satCartState = {
    // id => { id, costo, peso }
    items: new Map(),
  };

  const hasCartList   = !!ROUTES.cartList;
  const hasCartAdd    = !!ROUTES.cartAdd;
  const hasCartRemove = !!ROUTES.cartRemove;

  async function cartPost(url, payload) {
    if (!url) throw new Error('Ruta de carrito no configurada.');

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload || {}),
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      const err = new Error(data.msg || data.message || 'Error en la operación de carrito.');
      err.data = data;
      err.status = res.status;
      throw err;
    }
    return data;
  }

  function satCartMarkButton(btn, inCart) {
    if (!btn) return;
    const sr = btn.querySelector('.sr-only');
    if (inCart) {
      btn.classList.add('is-in-cart');
      btn.setAttribute('data-tip', 'En carrito (clic para quitar)');
      btn.setAttribute('aria-pressed', 'true');
      if (sr) sr.textContent = 'Quitar del carrito';
    } else {
      btn.classList.remove('is-in-cart');
      btn.setAttribute('data-tip', 'Agregar al carrito');
      btn.setAttribute('aria-pressed', 'false');
      if (sr) sr.textContent = 'Agregar al carrito';
    }
  }

  function satCartRecalcAndRender() {
    let total = 0;
    let totalPeso = 0;
    const count = satCartState.items.size;

    satCartState.items.forEach(item => {
      total += item.costo || 0;
      if (!isNaN(item.peso)) totalPeso += item.peso || 0;
    });

    // 1) Widget dentro del módulo SAT
    const spanCount  = document.querySelector('#satCartCount');
    const spanTotal  = document.querySelector('#satCartTotal');
    const spanWeight = document.querySelector('#satCartWeight');
    if (spanCount)  spanCount.textContent  = String(count);
    if (spanTotal)  spanTotal.textContent  = '$' + total.toFixed(2);
    if (spanWeight) spanWeight.textContent = totalPeso.toFixed(2) + ' MB';

    // 2) Header global cliente
    const headerBox = document.getElementById('satCartHeader');
    const headCount = document.getElementById('satCartHeaderCount');
    if (headerBox) headerBox.style.display = count > 0 ? 'flex' : 'none';
    if (headCount) headCount.textContent = String(count);

    // 3) Botones de tabla
    const body = document.getElementById('satDlBody');
    if (!body) return;
    body.querySelectorAll('tr[data-id]').forEach(tr => {
      const id = tr.getAttribute('data-id');
      const btn = tr.querySelector('.sat-btn-cart');
      const inCart = id && satCartState.items.has(id);
      satCartMarkButton(btn, !!inCart);
    });
  }

  function satCartApplyServerSummary(summary) {
    const cart = (summary && (summary.cart || summary)) || {};
    const rows = Array.isArray(cart.rows) ? cart.rows : [];
    const ids  = Array.isArray(cart.ids)  ? cart.ids  : [];

    satCartState.items.clear();

    if (rows.length) {
      rows.forEach(row => {
        const id = String(row.id ?? row.download_id ?? '');
        if (!id) return;
        const rawCost = row.costo ?? row.cost ?? row.price ?? 0;
        const rawPeso = row.peso  ?? row.weight_mb ?? row.size_mb ?? 0;
        satCartState.items.set(id, {
          id,
          costo: parseFloat(rawCost) || 0,
          peso:  parseFloat(rawPeso) || 0,
        });
      });
    } else if (ids.length) {
      ids.forEach(rawId => {
        const id = String(rawId);
        if (!id) return;

        let costoNum = 0;
        let pesoNum  = 0;
        const tr = document.querySelector(`tr[data-id="${id}"]`);
        if (tr) {
          costoNum = parseFloat(tr.getAttribute('data-costo') || '0') || 0;
          pesoNum  = parseFloat(tr.getAttribute('data-peso')  || '0') || 0;
        }
        satCartState.items.set(id, { id, costo: costoNum, peso: pesoNum });
      });
    }

    satCartRecalcAndRender();
  }

  async function satCartSyncFromServer() {
    if (!hasCartList) return;
    try {
      const res = await fetch(ROUTES.cartList, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) return;
      satCartApplyServerSummary(data.cart || data);
    } catch (e) {
      console.error('[SAT-CART] sync error', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', satCartSyncFromServer);
  } else {
    satCartSyncFromServer();
  }

  // ============================================================
  // Countdowns de disponibilidad (1 sola implementación)
  // ============================================================
  function initAvailabilityCountdowns() {
    const nodes = document.querySelectorAll('.sat-disp[data-exp]');
    nodes.forEach(node => {
      const expStr = node.getAttribute('data-exp');
      const exp = parseIsoDate(expStr);
      if (!exp) return;

      if (node._satTimerId) clearInterval(node._satTimerId);

      const tick = () => {
        const now = new Date();
        const diff = exp.getTime() - now.getTime();

        if (diff <= 0) {
          node.textContent = 'Expirada';
          node.classList.remove('sat-disp-active');
          node.classList.add('sat-disp-expired');
          node.removeAttribute('data-exp');

          // eliminar fila y sacar del carrito
          const tr = node.closest('tr');
          if (tr) {
            const id = tr.getAttribute('data-id');
            if (id && satCartState.items.has(id)) {
              satCartState.items.delete(id);
              satCartRecalcAndRender();
            }
            tr.remove();
          }

          clearInterval(node._satTimerId);
          node._satTimerId = null;
          return;
        }

        node.textContent = formatCountdown(diff);
        node.classList.add('sat-disp-active');
      };

      tick();
      node._satTimerId = setInterval(tick, 1000);
    });
  }

  // ============================================================
  // Listado de descargas SAT (tabla)
  // ============================================================
  (function initDownloadsTable() {
    const body = document.getElementById('satDlBody');
    if (!body) return;

    const qInput    = document.getElementById('satDlSearch');
    const tipoSel   = document.getElementById('satDlTipo');
    const statusSel = document.getElementById('satDlStatus');

    const allRows = Array.from(body.querySelectorAll('tr'));
    const addSelectedBtn = document.getElementById('satDlAddSelected');
    const bulkBuyBtn     = document.getElementById('satDlBulkBuy');

    function applyFilters() {
      const q  = (qInput?.value || '').toLowerCase();
      const ft = (tipoSel?.value || '').toLowerCase();
      const fs = (statusSel?.value || '').toLowerCase();

      allRows.forEach(tr => {
        let show = true;
        const search = (tr.dataset.search || '').toLowerCase();
        const tipo   = (tr.dataset.tipo || '').toLowerCase();
        const stat   = (tr.dataset.status || '').toLowerCase();

        if (q && !search.includes(q)) show = false;
        if (ft && tipo && tipo !== ft) show = false;
        if (fs && stat && stat !== fs) show = false;

        tr.style.display = show ? '' : 'none';
      });
    }

    if (qInput)    qInput.addEventListener('input', applyFilters);
    if (tipoSel)   tipoSel.addEventListener('change', applyFilters);
    if (statusSel) statusSel.addEventListener('change', applyFilters);

    // --- Cancelar / Carrito / Descargar ---
    async function handleCancel(btn) {
      if (!ROUTES.cancel) {
        satToast('Ruta de cancelación no configurada.', 'error');
        return;
      }

      const id = (btn.getAttribute('data-id') || '').trim();
      if (!id) return;

      const ok = confirm('¿Seguro que deseas cancelar y eliminar esta solicitud?');
      if (!ok) return;

      const tr = btn.closest('tr');
      btn.disabled = true;

      try {
        const res = await fetch(ROUTES.cancel, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ id, download_id: id }),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) {
          satToast(data.msg || data.message || 'No se pudo cancelar la solicitud.', 'error');
          return;
        }

        if (satCartState.items.has(id)) {
          satCartState.items.delete(id);
          satCartRecalcAndRender();
        }

        if (tr) tr.remove();
        satToast(data.msg || 'Solicitud cancelada.', 'ok');
      } catch (e) {
        console.error(e);
        satToast('Error de conexión al cancelar.', 'error');
      } finally {
        btn.disabled = false;
      }
    }

    async function handleAddToCart(btn) {
      const tr = btn.closest('tr');
      if (!tr || btn.disabled) return;

      const id = (tr.getAttribute('data-id') || '').trim();
      if (!id) return;

      const costoNum = parseFloat(tr.getAttribute('data-costo') || '0') || 0;
      const pesoNum  = parseFloat(tr.getAttribute('data-peso')  || '0') || 0;

      const yaEnCarro = satCartState.items.has(id);

      // Quitar
      if (yaEnCarro) {
        satCartState.items.delete(id);
        satCartRecalcAndRender();

        if (!hasCartRemove) {
          satCartMarkButton(btn, false);
          return;
        }

        try {
          btn.disabled = true;
          const data = await cartPost(ROUTES.cartRemove, { id, download_id: id });
          if (data.cart) satCartApplyServerSummary(data.cart);
          satToast(data.msg || 'Carrito actualizado.', 'ok');
        } catch (e) {
          console.error(e);
          satToast(e.message || 'No se pudo quitar del carrito.', 'error');
          satCartState.items.set(id, { id, costo: costoNum, peso: pesoNum }); // revert
          satCartRecalcAndRender();
        } finally {
          btn.disabled = false;
        }

        return;
      }

      // Agregar
      satCartState.items.set(id, { id, costo: costoNum, peso: pesoNum });
      satCartRecalcAndRender();

      if (!hasCartAdd) {
        satCartMarkButton(btn, true);
        return;
      }

      try {
        btn.disabled = true;
        const data = await cartPost(ROUTES.cartAdd, { id, download_id: id });

        // si devuelves zip_url => descarga directa
        if (data.zip_url) {
          window.location.href = data.zip_url;
          return;
        }

        // si devuelves checkout_url => ir a Stripe
        const checkoutUrl = data.checkout_url || data.url || data.session_url;
        if (checkoutUrl) {
          window.location.href = checkoutUrl;
          return;
        }

        if (data.cart) satCartApplyServerSummary(data.cart);
        satToast(data.msg || 'Descargas agregadas al carrito.', 'ok');
      } catch (e) {
        console.error(e);
        satToast(e.message || 'Error al procesar el carrito.', 'error');
        satCartState.items.delete(id);
        satCartRecalcAndRender();
      } finally {
        btn.disabled = false;
      }
    }

    // Modal bonito para decisiones de Bóveda / descarga
    function satVaultDialog(options) {
      const opts = options || {};
      const titleText    = opts.title || 'Descarga de CFDI';
      const bodyText     = opts.body || '';
      const primaryLabel = opts.primary || 'Aceptar';
      const secondaryLbl = opts.secondary || 'Cancelar';

      let modal = document.getElementById('satVaultModal');
      if (!modal) {
        modal = document.createElement('div');
        modal.id = 'satVaultModal';
        modal.className = 'sat-vault-modal';
        modal.innerHTML = `
          <div class="sat-vault-panel" role="dialog" aria-modal="true">
            <div class="sat-vault-header">
              <div class="sat-vault-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path fill="currentColor"
                    d="M12 2a5 5 0 0 1 5 5v2h1.25A2.75 2.75 0 0 1 21 11.75v7.5A2.75 2.75 0 0 1 18.25 22h-12.5A2.75 2.75 0 0 1 3 19.25v-7.5A2.75 2.75 0 0 1 5.75 9H7V7a5 5 0 0 1 5-5Zm0 2.25A2.75 2.75 0 0 0 9.25 7v2h5.5V7A2.75 2.75 0 0 0 12 4.25Zm0 8a1.75 1.75 0 0 1 .75 3.33v1.42a.75.75 0 0 1-1.5 0v-1.42A1.75 1.75 0 0 1 12 12.25Z" />
                </svg>
              </div>
              <div class="sat-vault-title" id="satVaultTitle"></div>
            </div>
            <div class="sat-vault-body" id="satVaultBody"></div>
            <div class="sat-vault-footer">
              <button type="button" class="sat-vault-btn sat-vault-btn-ghost" id="satVaultSecondary"></button>
              <button type="button" class="sat-vault-btn sat-vault-btn-primary" id="satVaultPrimary"></button>
            </div>
          </div>
        `;
        document.body.appendChild(modal);
      }

      const titleEl = modal.querySelector('#satVaultTitle');
      const bodyEl  = modal.querySelector('#satVaultBody');
      const pBtn    = modal.querySelector('#satVaultPrimary');
      const sBtn    = modal.querySelector('#satVaultSecondary');

      titleEl.textContent = titleText;
      bodyEl.innerHTML    = bodyText;
      pBtn.textContent    = primaryLabel;
      sBtn.textContent    = secondaryLbl;

      modal.classList.add('is-open');

      return new Promise((resolve) => {
        function cleanup(result) {
          modal.classList.remove('is-open');
          pBtn.removeEventListener('click', onPrimary);
          sBtn.removeEventListener('click', onSecondary);
          modal.removeEventListener('click', onBackdrop);
          document.removeEventListener('keydown', onKey);
          resolve(result);
        }
        function onPrimary() { cleanup('primary'); }
        function onSecondary() { cleanup('secondary'); }
        function onBackdrop(e) { if (e.target === modal) cleanup('secondary'); }
        function onKey(e) {
          if (e.key === 'Escape') cleanup('secondary');
          if (e.key === 'Enter') cleanup('primary');
        }

        pBtn.addEventListener('click', onPrimary);
        sBtn.addEventListener('click', onSecondary);
        modal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKey);
        pBtn.focus();
      });
    }

    async function handleDownload(dlBtn) {
      if (!dlBtn) return;

      const id  = (dlBtn.getAttribute('data-id') || '').trim();
      let url   = (dlBtn.getAttribute('data-url') || '').trim();

      // 1) Resolver URL base de descarga
      let finalUrl = '';
      if (url && url !== '#') {
        finalUrl = url;
      } else {
        const zipPat  = ROUTES.zipPattern || '';
        const dlRoute = ROUTES.download || '';

        if (id && zipPat && zipPat !== '#') {
          finalUrl = zipPat;
          if (finalUrl.includes('__ID__')) finalUrl = finalUrl.replace('__ID__', encodeURIComponent(id));
          else if (finalUrl.includes('{id}')) finalUrl = finalUrl.replace('{id}', encodeURIComponent(id));
          else finalUrl = finalUrl.replace(/\/+$/, '') + '/' + encodeURIComponent(id);
        } else if (id && dlRoute) {
          finalUrl = dlRoute;
          if (finalUrl.includes('__ID__')) finalUrl = finalUrl.replace('__ID__', encodeURIComponent(id));
          else if (finalUrl.includes('{id}')) finalUrl = finalUrl.replace('{id}', encodeURIComponent(id));
          else if (finalUrl.includes('?')) finalUrl += '&id=' + encodeURIComponent(id);
          else finalUrl = finalUrl.replace(/\/+$/, '') + '/' + encodeURIComponent(id);
        }
      }

      if (!finalUrl) {
        satToast('No se encontró la URL de descarga para este paquete.', 'error');
        return;
      }

      const vaultActive = !!VAULT.active || Number(VAULT.quota_gb || VAULT.quota || 0) > 0;
      const vaultRoute  = ROUTES.vaultIndex || '/cliente/sat/vault';

      // 2.a) Cuenta con bóveda activa: ¿guardar o sólo descargar?
      if (vaultActive) {
        const result = await satVaultDialog({
          title: '¿Cómo quieres descargar tus CFDI?',
          body: '<strong>Puedes guardar el ZIP en tu Bóveda fiscal</strong> para tener todos los CFDI organizados, o sólo descargarlo sin usar almacenamiento.',
          primary: 'Descargar y guardar en bóveda',
          secondary: 'Sólo descargar ZIP',
        });

        if (result === 'primary') {
          try {
            await vaultStoreFromDownload(id);
            satToast('Guardado en Bóveda. Iniciando descarga...', 'ok');
          } catch (e) {
            console.error('[SAT-DASH] vaultStoreFromDownload error', e);
            satToast(e.message || 'No se pudo guardar en Bóveda. Se descargará sólo el ZIP.', 'error');
          }
        }

        window.location.href = finalUrl;
        return;
      }

      // 2.b) Sin bóveda activa: ofrecer activarla
      const result = await satVaultDialog({
        title: 'Activa tu Bóveda fiscal',
        body: 'Aún no tienes la Bóveda fiscal activa. <strong>Puedes ir a activarla</strong> para almacenar todos tus CFDI, o bien <strong>sólo descargar el ZIP</strong> sin usar almacenamiento.',
        primary: 'Ir a activar bóveda',
        secondary: 'Sólo descargar ZIP',
      });

      if (result === 'primary') window.location.href = vaultRoute;
      else window.location.href = finalUrl;
    }

    // --- Bulk add seleccionados (PROMESA REAL) ---
    async function bulkAddSelected() {
      const checks = Array.from(body.querySelectorAll('.sat-dl-check'));
      const selected = checks.filter(cb => cb.checked);

      if (!selected.length) {
        satToast('Selecciona al menos 1 solicitud.', 'error');
        return { added: 0 };
      }

      let added = 0;
      let skippedNoId = 0;
      let skippedNoCart = 0;

      for (const cb of selected) {
        const tr = cb.closest('tr');
        if (!tr) continue;

        const id = (tr.getAttribute('data-id') || '').trim();
        if (!id) {
          skippedNoId++;
          continue;
        }

        const cartBtn = tr.querySelector('.sat-btn-cart');
        if (!cartBtn || cartBtn.disabled) {
          skippedNoCart++;
          continue;
        }

        if (cartBtn.classList.contains('is-in-cart')) continue;

        try {
          await handleAddToCart(cartBtn);
          added++;
        } catch (_) {}
      }

      if (skippedNoId > 0) satToast(`Se omitieron ${skippedNoId} filas sin ID válido.`, 'error');
      if (skippedNoCart > 0) satToast(`Se omitieron ${skippedNoCart} filas que no están listas para compra.`, 'error');
      if (added > 0) satToast(`Agregadas ${added} descargas al carrito.`, 'ok');

      return { added, skippedNoId, skippedNoCart };
    }

    if (addSelectedBtn) {
      addSelectedBtn.addEventListener('click', async () => {
        addSelectedBtn.disabled = true;
        try {
          await bulkAddSelected();
        } finally {
          addSelectedBtn.disabled = false;
        }
      });
    }

    if (bulkBuyBtn) {
      bulkBuyBtn.addEventListener('click', async () => {
        bulkBuyBtn.disabled = true;
        try {
          await bulkAddSelected();
          if (ROUTES.cartIndex) {
            window.location.href = ROUTES.cartIndex;
          } else {
            satToast('Ruta cartIndex no configurada.', 'error');
          }
        } finally {
          bulkBuyBtn.disabled = false;
        }
      });
    }

    // Delegación de clicks en la tabla
    body.addEventListener('click', (ev) => {
      const cancelBtn = ev.target.closest('.sat-btn-cancel');
      if (cancelBtn) {
        ev.preventDefault();
        handleCancel(cancelBtn);
        return;
      }

      const cartBtn = ev.target.closest('.sat-btn-cart');
      if (cartBtn) {
        ev.preventDefault();
        handleAddToCart(cartBtn);
        return;
      }

      const dlBtn = ev.target.closest('.sat-btn-download');
      if (dlBtn) {
        ev.preventDefault();
        // sin bloquear el hilo
        handleDownload(dlBtn);
      }
    });

    applyFilters();
    initAvailabilityCountdowns();
  })();

  // ============================================================
  // Cambiar modo DEMO / PROD
  // ============================================================
  (function initModeToggle() {
    const badge = document.getElementById('badgeMode');
    if (!badge || !ROUTES.mode) return;

    badge.addEventListener('click', async () => {
      try {
        const res = await fetch(ROUTES.mode, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
          },
        });
        const j = await res.json().catch(() => null);
        if (j && j.mode) location.reload();
      } catch (e) {
        console.error(e);
        alert('No se pudo cambiar el modo.');
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
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
          },
        });
        const j = await r.json().catch(() => ({}));
        alert(`Pendientes: ${j.pending ?? 0} · Listos: ${j.ready ?? 0}`);
        location.reload();
      } catch (e) {
        console.error(e);
        alert('No se pudo verificar');
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
            alert('En FREE sólo puedes solicitar hasta 1 mes por ejecución.');
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
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
          },
          body: fd,
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok === false) {
          alert(data?.msg || data?.message || 'No se pudo crear la solicitud SAT.');
          return;
        }

        location.reload();
      } catch (e) {
        console.error(e);
        alert('Error de conexión al enviar la solicitud.');
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

    const open  = () => modal.classList.add('is-open');
    const close = () => modal.classList.remove('is-open');

    document.addEventListener('click', (ev) => {
      const openBtn  = ev.target.closest('[data-open="add-rfc"]');
      const closeBtn = ev.target.closest('[data-close="modal-rfc"]');

      if (openBtn) { ev.preventDefault(); open(); }
      if (closeBtn || ev.target === modal) { ev.preventDefault(); close(); }
    });

    window.addEventListener('sat-open-add-rfc', open);

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      const fd = new FormData(form);

      const hasCer = fd.get('cer') instanceof File && fd.get('cer').name;
      const hasKey = fd.get('key') instanceof File && fd.get('key').name;
      const pwd    = (fd.get('key_password') || '').toString().trim();
      const useCsd = (hasCer || hasKey || pwd !== '');

      const url = useCsd ? ROUTES.csdStore : ROUTES.rfcReg;

      if (!url) {
        alert('Ruta de guardado de RFC no configurada.');
        if (submitBtn) submitBtn.disabled = false;
        return;
      }

      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: fd,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || (data && data.ok === false)) {
          alert(data?.msg || data?.message || 'No se pudo guardar el RFC / CSD.');
          return;
        }

        close();
        if (data && data.redirect) window.location.href = data.redirect;
        else location.reload();
      } catch (e) {
        console.error(e);
        alert('Error de conexión al enviar los datos.');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  })();

  // ============================================================
  // Gráficas (Chart.js) – se dejan como están, sólo robustas
  // ============================================================
  (function initTrendsCharts() {
    const hasHttp = !!ROUTES.charts;
    if (typeof Chart === 'undefined') return;

    function mkChart(canvasId, label) {
      const el = document.getElementById(canvasId);
      if (!el) return null;

      return new Chart(el, {
        type: 'line',
        data: { labels: [], datasets: [{ label, data: [], borderWidth: 2, tension: 0.35, pointRadius: 4, pointHoverRadius: 5 }] },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: 'rgba(148,163,184,.25)' } } },
        },
      });
    }

    const chartA = mkChart('chartA', 'Importe total');
    const chartB = mkChart('chartB', '# CFDI');
    if (!chartA || !chartB) return;

    function getFallback() {
      return { labels: ['P1','P2','P3','P4','P5','P6'], counts: [0,0,0,0,0,0], amounts: [0,0,0,0,0,0] };
    }

    function applyToCharts(labels, amounts, counts, meta = {}) {
      chartA.data.labels = labels;
      chartB.data.labels = labels;
      chartA.data.datasets[0].label = meta.label_amount || 'Importe total';
      chartB.data.datasets[0].label = meta.label_count  || '# CFDI';
      chartA.data.datasets[0].data = amounts;
      chartB.data.datasets[0].data = counts;
      chartA.update();
      chartB.update();

      if (window._satMovChart) {
        window._satMovChart.data.labels = labels;
        window._satMovChart.data.datasets[0].data = counts;
        window._satMovChart.update();
      }
    }

    async function loadScope(scope) {
      if (!hasHttp) {
        const fb = getFallback();
        applyToCharts(fb.labels, fb.amounts, fb.counts);
        return;
      }

      try {
        const url = ROUTES.charts + '?scope=' + encodeURIComponent(scope || 'emitidos');
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

        if (!res.ok) {
          const fb = getFallback();
          applyToCharts(fb.labels, fb.amounts, fb.counts);
          return;
        }

        const j = await res.json().catch(() => ({}));
        const labels = Array.isArray(j.labels) ? j.labels : [];
        const series = j.series || {};
        const amounts = Array.isArray(series.amounts) ? series.amounts : [];
        const counts  = Array.isArray(series.counts)  ? series.counts  : [];

        if (!labels.length) {
          const fb = getFallback();
          applyToCharts(fb.labels, fb.amounts, fb.counts);
          return;
        }

        applyToCharts(
          labels,
          amounts.length ? amounts : new Array(labels.length).fill(0),
          counts.length  ? counts  : new Array(labels.length).fill(0),
          { label_amount: series.label_amount, label_count: series.label_count }
        );
      } catch (e) {
        console.error('[SAT-DASH] charts error', e);
        const fb = getFallback();
        applyToCharts(fb.labels, fb.amounts, fb.counts);
      }
    }

    loadScope('emitidos');

    document.querySelectorAll('#block-trends .tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('#block-trends .tab').forEach(x => x.classList.remove('is-active'));
        tab.classList.add('is-active');
        loadScope(tab.dataset.scope || 'emitidos');
      });
    });
  })();

  // ============================================================
  // Gráfica "Últimas semanas" con rango de fechas
  // ============================================================
  (function initMovChartRange() {
    const canvas = document.getElementById('satMovChart');
    const inpFrom = document.getElementById('satMovFrom');
    const inpTo = document.getElementById('satMovTo');
    const btnAp = document.getElementById('satMovApply');

    if (!canvas || !inpFrom || !inpTo || !btnAp || typeof Chart === 'undefined') return;

    const hasChartsEndpoint = !!ROUTES.charts;

    const movChart = new Chart(canvas, {
      type: 'line',
      data: { labels: [], datasets: [{ label: 'CFDI descargados', data: [], borderWidth: 2, tension: 0.35, pointRadius: 4, pointHoverRadius: 5 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { grid: { color: 'rgba(148,163,184,.18)' } }, y: { grid: { color: 'rgba(148,163,184,.25)' } } },
      },
    });

    window._satMovChart = movChart;

    function applyToChart(labels, counts) {
      movChart.data.labels = labels;
      movChart.data.datasets[0].data = counts;
      movChart.update();
    }

    function fallbackData() {
      return { labels: ['P1','P2','P3','P4','P5','P6','P7'], counts: [0,0,0,0,0,0,0] };
    }

    async function loadRange(from, to) {
      const dummy = fallbackData();
      applyToChart(dummy.labels, dummy.counts);

      if (!hasChartsEndpoint) return;

      try {
        const url = ROUTES.charts
          + '?scope=' + encodeURIComponent('emitidos')
          + '&period=' + encodeURIComponent('range')
          + '&from=' + encodeURIComponent(from)
          + '&to=' + encodeURIComponent(to);

        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) return;

        const j = await res.json().catch(() => ({}));
        const labels = Array.isArray(j.labels) && j.labels.length ? j.labels : dummy.labels;
        const series = j.series || {};
        const counts = Array.isArray(series.counts) && series.counts.length ? series.counts : dummy.counts;

        applyToChart(labels, counts);
      } catch (e) {
        console.error('[SAT-DASH] movChart range error', e);
      }
    }

    function fmt(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const a = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${a}`;
    }

    (function initDefaultRange() {
      const today = new Date();
      const past = new Date();
      past.setDate(today.getDate() - 30);

      const vTo = fmt(today);
      const vFrom = fmt(past);

      inpFrom.value = vFrom;
      inpTo.value = vTo;

      if (hasChartsEndpoint) loadRange(vFrom, vTo);
      else {
        const d = fallbackData();
        applyToChart(d.labels, d.counts);
      }
    })();

    btnAp.addEventListener('click', () => {
      const from = (inpFrom.value || '').trim();
      const to = (inpTo.value || '').trim();

      if (!from || !to) { alert('Selecciona fecha inicial y final.'); return; }
      if (new Date(from) > new Date(to)) { alert('La fecha inicial no puede ser mayor que la final.'); return; }

      loadRange(from, to);
    });
  })();

  // ============================================================
  // Donut bóveda fiscal
  // ============================================================
  (function initVaultDonut() {
    const el = document.getElementById('vaultDonut');
    if (!el || typeof Chart === 'undefined' || !VAULT) return;

    const used = Number(VAULT.used || 0);
    const free = Number(VAULT.free || 0);

    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: ['Consumido', 'Disponible'],
        datasets: [{ data: [used, free], borderWidth: 0, backgroundColor: ['#ec4899', '#dbeafe'] }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { enabled: true } },
      },
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
    const open  = () => modal.classList.add('is-open');
    const close = () => modal.classList.remove('is-open');

    document.addEventListener('click', (e) => {
      const openBtn = e.target.closest('.auto-modal-btn');
      if (openBtn) { e.preventDefault(); open(); }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => { e.preventDefault(); close(); });
    }

    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
  })();

  // ============================================================
  // Carrito – botón "Pagar" (redirige a carrito o checkout)
  // ============================================================
  (function initCartPay() {
    const btnPay = document.querySelector('#satCartPay, [data-sat-cart-pay]');
    if (!btnPay) return;

    btnPay.addEventListener('click', async (e) => {
      e.preventDefault();

      const isCartView = window.location.pathname.includes('/sat/cart');

      // 1) Si NO estoy en carrito → ir a /cliente/sat/cart
      if (!isCartView && ROUTES.cartIndex) {
        window.location.href = ROUTES.cartIndex;
        return;
      }

      // 2) Ya estoy en carrito → checkout (Stripe)
      const url = ROUTES.cartPay || ROUTES.cartCheckout || null;
      if (!url) {
        satToast('No se encontró la ruta de pago SAT. Avísale a soporte.', 'error');
        return;
      }

      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({}),
        });

        const isJson = (res.headers.get('content-type') || '').includes('application/json');
        if (!isJson) {
          window.location.href = res.url || url;
          return;
        }

        const data = await res.json().catch(() => ({}));
        if (!data.ok) {
          satToast(data.msg || data.message || 'No se pudo procesar el carrito.', 'error');
          return;
        }

        const checkoutUrl = data.checkout_url || data.url || data.session_url;
        if (checkoutUrl) {
          window.location.href = checkoutUrl;
          return;
        }

        satToast(data.msg || 'Carrito listo para pago.', 'ok');
      } catch (err) {
        console.error(err);
        satToast('Error al contactar el servidor. Intenta de nuevo.', 'error');
      }
    });
  })();

  // ============================================================
  // CTA "Activar / Ampliar bóveda" en dashboard principal
  // ============================================================
  (function initVaultCta() {
    const btn = document.getElementById('btnVaultSummaryCta') || document.querySelector('.btn-vault-cta');
    if (!btn) return;

    const vaultUrl = ROUTES.vaultIndex || btn.getAttribute('data-vault-url') || null;
    if (!vaultUrl || vaultUrl === '#' || vaultUrl === 'null') return;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = vaultUrl;
    });
  })();

})();
