// public/assets/client/js/sat/sat-cart-downloads.js
// Carrito SAT + tabla de descargas + countdowns + diálogo bóveda/descarga

(() => {
  'use strict';

  const APP = window.P360_SAT_APP || {};
  const ROUTES = APP.ROUTES || {};
  const CSRF   = APP.CSRF || '';
  const VAULT  = APP.VAULT || {};
  const satToast = APP.satToast || function(){};

  const parseIsoDate = APP.parseIsoDate || (() => null);
  const formatCountdown = APP.formatCountdown || (() => '00:00:00');
  const safeJson = APP.safeJson || (async () => ({}));
  const mkHttpError = APP.mkHttpError || ((_, __, m) => new Error(m || 'Error'));
  const isJsonResponse = APP.isJsonResponse || (() => false);
  const vaultStoreFromDownload = APP.vaultStoreFromDownload || (async () => ({}));

  // ============================================================
  // CARRITO SAT – estado global
  // ============================================================
  const satCartState = {
    items: new Map(), // id => { id, costo, peso }
  };

  APP.satCartState = satCartState;

  const hasCartList   = !!ROUTES.cartList;
  const hasCartAdd    = !!ROUTES.cartAdd;
  const hasCartRemove = !!ROUTES.cartRemove;

  async function cartPost(url, payload) {
    if (!url) throw new Error('Ruta de carrito no configurada.');

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload || {}),
    });

    const data = await safeJson(res);

    if (data && data._non_json) throw mkHttpError(res, data, 'Respuesta no JSON para carrito.');
    if (!res.ok || data.ok === false) throw mkHttpError(res, data, 'Error en la operación de carrito.');

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

    const spanCount  = document.querySelector('#satCartCount');
    const spanTotal  = document.querySelector('#satCartTotal');
    const spanWeight = document.querySelector('#satCartWeight');
    if (spanCount)  spanCount.textContent  = String(count);
    if (spanTotal)  spanTotal.textContent  = '$' + total.toFixed(2);
    if (spanWeight) spanWeight.textContent = totalPeso.toFixed(2) + ' MB';

    const headerBox = document.getElementById('satCartHeader');
    const headCount = document.getElementById('satCartHeaderCount');
    if (headerBox) headerBox.style.display = count > 0 ? 'flex' : 'none';
    if (headCount) headCount.textContent = String(count);

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
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      });

      const data = await safeJson(res);

      if (data && data._non_json) {
        console.error('[SAT-CART] list non-json response', { status: res.status, text: data._text });
        return;
      }

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
  // Countdowns de disponibilidad
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
  // Diálogo bonito (Bóveda / descarga)
  // ============================================================
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

  // ============================================================
  // Tabla de descargas SAT
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
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ id, download_id: id }),
        });

        const data = await safeJson(res);

        if (data && data._non_json) {
          satToast('Cancelación falló (respuesta no JSON). Revisa sesión/CSRF.', 'error');
          console.error('[SAT-CANCEL] non-json response', { status: res.status, text: data._text });
          return;
        }

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

    // Retorna true si quedó EN carrito; false si no
    async function handleAddToCart(btn) {
      const tr = btn.closest('tr');
      if (!tr || btn.disabled) return false;

      const id = (tr.getAttribute('data-id') || '').trim();
      if (!id) return false;

      const costoNum = parseFloat(tr.getAttribute('data-costo') || '0') || 0;
      const pesoNum  = parseFloat(tr.getAttribute('data-peso')  || '0') || 0;

      const yaEnCarro = satCartState.items.has(id);

      // Quitar
      if (yaEnCarro) {
        satCartState.items.delete(id);
        satCartRecalcAndRender();

        if (!hasCartRemove) {
          satCartMarkButton(btn, false);
          return false;
        }

        try {
          btn.disabled = true;
          const data = await cartPost(ROUTES.cartRemove, { id, download_id: id });
          if (data.cart) satCartApplyServerSummary(data.cart);
          satToast(data.msg || 'Carrito actualizado.', 'ok');
          return false;
        } catch (e) {
          console.error(e);
          satToast(e.message || 'No se pudo quitar del carrito.', 'error');
          satCartState.items.set(id, { id, costo: costoNum, peso: pesoNum });
          satCartRecalcAndRender();
          return true;
        } finally {
          btn.disabled = false;
        }
      }

      // Agregar (optimista)
      satCartState.items.set(id, { id, costo: costoNum, peso: pesoNum });
      satCartRecalcAndRender();

      if (!hasCartAdd) {
        satCartMarkButton(btn, true);
        return true;
      }

      try {
        btn.disabled = true;
        const data = await cartPost(ROUTES.cartAdd, { id, download_id: id });

        if (data.zip_url) {
          window.location.href = data.zip_url;
          return true;
        }

        const checkoutUrl = data.checkout_url || data.url || data.session_url;
        if (checkoutUrl) {
          window.location.href = checkoutUrl;
          return true;
        }

        if (data.cart) satCartApplyServerSummary(data.cart);
        satToast(data.msg || 'Descargas agregadas al carrito.', 'ok');
        return true;
      } catch (e) {
        console.error(e);
        satToast(e.message || 'Error al procesar el carrito.', 'error');
        satCartState.items.delete(id);
        satCartRecalcAndRender();
        return false;
      } finally {
        btn.disabled = false;
      }
    }

    async function handleDownload(dlBtn) {
      if (!dlBtn) return;

      const id  = (dlBtn.getAttribute('data-id') || '').trim();
      let url   = (dlBtn.getAttribute('data-url') || '').trim();

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
            console.error('[SAT] vaultStoreFromDownload error', e);
            satToast(e.message || 'No se pudo guardar en Bóveda. Se descargará sólo el ZIP.', 'error');
          }
        }

        window.location.href = finalUrl;
        return;
      }

      const result = await satVaultDialog({
        title: 'Activa tu Bóveda fiscal',
        body: 'Aún no tienes la Bóveda fiscal activa. <strong>Puedes ir a activarla</strong> para almacenar todos tus CFDI, o bien <strong>sólo descargar el ZIP</strong> sin usar almacenamiento.',
        primary: 'Ir a activar bóveda',
        secondary: 'Sólo descargar ZIP',
      });

      if (result === 'primary') window.location.href = vaultRoute;
      else window.location.href = finalUrl;
    }

    async function bulkAddSelected() {
      const checks = Array.from(body.querySelectorAll('.sat-dl-check'));
      const selected = checks.filter(cb => cb.checked);

      if (!selected.length) {
        satToast('Selecciona al menos 1 solicitud.', 'error');
        return { added: 0, skippedNoId: 0, skippedNoCart: 0, alreadyInCart: 0, failed: 0 };
      }

      let added = 0;
      let skippedNoId = 0;
      let skippedNoCart = 0;
      let alreadyInCart = 0;
      let failed = 0;

      for (const cb of selected) {
        const tr = cb.closest('tr');
        if (!tr) continue;

        const id = (tr.getAttribute('data-id') || '').trim();
        if (!id) { skippedNoId++; continue; }

        const cartBtn = tr.querySelector('.sat-btn-cart');
        if (!cartBtn || cartBtn.disabled) { skippedNoCart++; continue; }

        if (cartBtn.classList.contains('is-in-cart')) { alreadyInCart++; continue; }

        const ok = await handleAddToCart(cartBtn);
        if (ok) added++;
        else failed++;
      }

      if (skippedNoId > 0) satToast(`Se omitieron ${skippedNoId} filas sin ID válido.`, 'error');
      if (skippedNoCart > 0) satToast(`Se omitieron ${skippedNoCart} filas que no están listas para compra.`, 'error');
      if (alreadyInCart > 0) satToast(`Ya estaban en carrito: ${alreadyInCart}.`, 'ok');
      if (failed > 0) satToast(`No se pudieron agregar: ${failed}.`, 'error');
      if (added > 0) satToast(`Agregadas ${added} descargas al carrito.`, 'ok');

      return { added, skippedNoId, skippedNoCart, alreadyInCart, failed };
    }

    if (addSelectedBtn) {
      addSelectedBtn.addEventListener('click', async () => {
        addSelectedBtn.disabled = true;
        try { await bulkAddSelected(); }
        finally { addSelectedBtn.disabled = false; }
      });
    }

    if (bulkBuyBtn) {
      bulkBuyBtn.addEventListener('click', async () => {
        bulkBuyBtn.disabled = true;
        try {
          await bulkAddSelected();
          if (ROUTES.cartIndex) window.location.href = ROUTES.cartIndex;
          else satToast('Ruta cartIndex no configurada.', 'error');
        } finally {
          bulkBuyBtn.disabled = false;
        }
      });
    }

    body.addEventListener('click', (ev) => {
      const cancelBtn = ev.target.closest('.sat-btn-cancel');
      if (cancelBtn) { ev.preventDefault(); handleCancel(cancelBtn); return; }

      const cartBtn = ev.target.closest('.sat-btn-cart');
      if (cartBtn) { ev.preventDefault(); handleAddToCart(cartBtn); return; }

      const dlBtn = ev.target.closest('.sat-btn-download');
      if (dlBtn) { ev.preventDefault(); handleDownload(dlBtn); }
    });

    applyFilters();
    initAvailabilityCountdowns();
  })();

  // Expone render por si otro módulo necesita refrescar UI
  APP.satCartRecalcAndRender = satCartRecalcAndRender;
  APP.satCartApplyServerSummary = satCartApplyServerSummary;
  APP.satCartSyncFromServer = satCartSyncFromServer;

})();
