// public/assets/client/js/sat-cart-pay.js
// PACTOPIA360 · SAT · Dashboard (cliente) · Carrito – botón "Pagar"

(() => {
  'use strict';

  window.P360_SAT = window.P360_SAT || {};
  const SAT = window.P360_SAT;

  const ROUTES = SAT.routes || {};
  const CSRF = SAT.csrf || '';

  const U = window.P360_SAT_UTILS || {};

  const satToast = U.satToast || U.toast || function (msg) {
    try { console.log('[SAT toast]', msg); } catch (_) {}
  };

  const isJsonResponse = U.isJsonResponse || function (res) {
    const ct = (res?.headers?.get?.('content-type') || '').toLowerCase();
    return ct.includes('application/json') || ct.includes('text/json') || ct.includes('+json');
  };

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

  const safeJson = U.safeJson || async function (res) {
    if (!res) return {};
    if (!isJsonResponse(res)) return { _non_json: true, _text: await safeText(res) };
    try {
      const j = await res.json();
      return (j && typeof j === 'object') ? j : {};
    } catch (_) {
      return { _non_json: true, _text: await safeText(res) };
    }
  };

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
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({}),
        });

        // Si no es JSON, probablemente es redirect / HTML
        if (!isJsonResponse(res)) {
          const loc = res.headers && (res.headers.get('Location') || res.headers.get('location'));
          window.location.href = loc || res.url || url;
          return;
        }

        const data = await safeJson(res);
        if (data && data._non_json) {
          window.location.href = res.url || url;
          return;
        }

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

})();
