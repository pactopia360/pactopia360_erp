/* public/assets/admin/js/admin-clientes.js
   Admin · Clientes — UI v15.0 (clean)
   - 1 solo flujo de eventos (sin duplicados)
   - currentClient robusto (row[data-client] + fallback global)
   - Drawer + Modales consistentes
   - Tabs + Copy + ESC + menú ⋯
   - Debug opcional: ?acdbg=1 o localStorage.acdbg=1 (solo console, sin overlay)
*/
(function () {
  'use strict';

  const VERSION = 'admin-clientes.js v15.0-clean';

  const $ = (s, sc) => (sc || document).querySelector(s);
  const $$ = (s, sc) => Array.from((sc || document).querySelectorAll(s));

  const page = $('#adminClientesPage');
  if (!page) return;

    // =========================================================
  // Export CSV (client-side) - reemplaza vnext.page.js
  // =========================================================
  const safeCsv = (v) => {
    if (v === null || v === undefined) return '';
    return String(v).replace(/\r?\n/g, ' ').trim();
  };

  const csvEscape = (v) => {
    v = safeCsv(v);
    if (/[",\n]/.test(v)) return `"${v.replace(/"/g, '""')}"`;
    return v;
  };

  const getRowsData = () => {
    const rows = $$('.ac-row[data-client]');
    const out = [];
    for (const row of rows) {
      const c = parseClientFromRow(row);
      if (c) out.push(c);
    }
    return out;
  };

  const buildCsv = (data) => {
    const cols = [
      ['id', 'ID'],
      ['rfc', 'RFC'],
      ['razon_social', 'RAZON_SOCIAL'],
      ['email', 'EMAIL'],
      ['phone', 'TELEFONO'],
      ['plan', 'PLAN'],
      ['billing_cycle_label', 'CICLO'],
      ['billing_status_label', 'BILLING_STATUS'],
      ['next_invoice_label', 'PROX_FACTURA'],
      ['blocked', 'BLOQUEADO'],
      ['custom_amount_mxn', 'MONTO_CUSTOM_MXN'],
      ['effective_amount_mxn', 'MONTO_EFECTIVO_MXN'],
      ['estado_cuenta', 'ESTADO_CUENTA'],
      ['modo_cobro', 'MODO_COBRO'],
      ['stripe_customer_id', 'STRIPE_CUSTOMER_ID'],
      ['stripe_subscription_id', 'STRIPE_SUBSCRIPTION_ID'],
      ['current_period_start', 'PERIODO_INICIO'],
      ['current_period_end', 'PERIODO_FIN'],
      ['recips_statement', 'RECIPS_ESTADO_CUENTA'],
      ['recips_invoice', 'RECIPS_FACTURA'],
      ['recips_general', 'RECIPS_GENERAL'],
      ['owner_email', 'OWNER_EMAIL'],
    ];

    const header = cols.map(c => csvEscape(c[1])).join(',');
    const lines = [header];

    for (const r of data) {
      const line = cols.map(([k]) => csvEscape(r?.[k]));
      lines.push(line.join(','));
    }
    return lines.join('\n');
  };

  const downloadCsv = (csvText) => {
    const blob = new Blob([csvText], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const stamp = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}`;

    const qp = new URLSearchParams(location.search);
    const q = (qp.get('q') || '').trim();
    const plan = (qp.get('plan') || '').trim();
    const blocked = (qp.get('blocked') || '').trim();
    const bs = (qp.get('billing_status') || '').trim();
    const pp = (qp.get('per_page') || '').trim();
    const pageN = (qp.get('page') || '').trim();

    const parts = [
      'clientes',
      stamp,
      plan ? `plan-${plan}` : null,
      blocked !== '' ? `blocked-${blocked}` : null,
      bs ? `billing-${bs}` : null,
      pp ? `pp-${pp}` : null,
      pageN ? `p-${pageN}` : null,
      q ? `q-${q.replace(/\s+/g, '_').slice(0, 24)}` : null,
    ].filter(Boolean);

    const filename = parts.join('_') + '.csv';

    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();

    setTimeout(() => URL.revokeObjectURL(url), 5000);
  };

  const handleExportCsv = () => {
    const btn = $('#btnExportCsv');
    if (btn) {
      btn.disabled = true;
      btn.dataset.prevText = btn.textContent;
      btn.textContent = 'Exportando…';
    }

    try {
      const data = getRowsData();
      if (!data.length) {
        alert('No hay filas en pantalla para exportar (página actual).');
        return;
      }
      downloadCsv(buildCsv(data));
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = btn.dataset.prevText || 'Exportar';
      }
    }
  };

  // =========================================================
  // Iframe modal (submódulos) - reemplaza vnext.page.js
  // =========================================================
  const openAcIframeModal = ({ title, subtitle, url }) => {
    const modal = $('#acIframeModal');
    const wrap = $('#acIf_wrap');
    const frame = $('#acIf_frame');
    const tEl = $('#acIf_title');
    const sEl = $('#acIf_sub');
    const openN = $('#acIf_open_new');
    const openN2 = $('#acIf_open_new_2');

    const fb = $('#acIf_fallback');
    const fbUrl = $('#acIf_fallback_url');
    const fbReason = $('#acIf_fallback_reason');
    const retryBtn = $('#acIf_retry');

    const status = $('#acIf_status');
    const statusText = $('#acIf_status_text');

    if (!modal || !wrap || !frame) return;
    url = String(url || '').trim();

    if (!url || url === '#') {
      alert('No hay URL disponible para este submódulo (falta route o payload).');
      return;
    }

    if (tEl) tEl.textContent = title || '—';
    if (sEl) sEl.textContent = subtitle || '—';
    if (openN) openN.href = url;
    if (openN2) openN2.href = url;

    if (fb) fb.hidden = true;
    if (fbUrl) fbUrl.textContent = url;
    if (fbReason) {
      fbReason.textContent = 'La página puede haber redirigido al login, cargado un layout no embebible o haber respondido con un error.';
    }

    if (status) {
      status.classList.remove('is-ready', 'is-error');
      status.classList.add('is-loading');
    }
    if (statusText) statusText.textContent = 'Cargando panel…';

    wrap.dataset.state = 'loading';

    openModal(modal);

    const token = String(Date.now()) + Math.random().toString(16).slice(2);
    frame.dataset.fbToken = token;
    frame.dataset.currentUrl = url;

    if (retryBtn) retryBtn.setAttribute('data-url', url);

    try {
      frame.onload = null;
      frame.onerror = null;
      frame.src = 'about:blank';
    } catch (_) {}

    const fail = (reason) => {
      if (!frame || frame.dataset.fbToken !== token) return;

      wrap.dataset.state = 'error';

      if (fb) fb.hidden = false;
      if (fbUrl) fbUrl.textContent = url;
      if (fbReason) fbReason.textContent = reason || 'No se pudo mostrar el panel dentro del modal.';

      if (status) {
        status.classList.remove('is-loading', 'is-ready');
        status.classList.add('is-error');
      }
      if (statusText) statusText.textContent = 'No se pudo cargar dentro del modal';
    };

    const success = () => {
      if (!frame || frame.dataset.fbToken !== token) return;

      if (fb) fb.hidden = true;
      wrap.dataset.state = 'ready';

      if (status) {
        status.classList.remove('is-loading', 'is-error');
        status.classList.add('is-ready');
      }
      if (statusText) statusText.textContent = 'Panel cargado';
    };

    const inspectFrame = () => {
      if (!frame || frame.dataset.fbToken !== token) return;

      try {
        const doc = frame.contentDocument || frame.contentWindow?.document;
        if (!doc) {
          success();
          return;
        }

        const href = String(frame.contentWindow?.location?.href || '');
        const titleText = String(doc.title || '').trim().toLowerCase();
        const bodyText = String(doc.body?.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 900).toLowerCase();

        const loginHints = [
          'iniciar sesión',
          'inicia sesión',
          'login',
          'sign in',
          'credenciales',
          'email',
          'contraseña',
        ];

        const errorHints = [
          'server error',
          'internal server error',
          '419',
          '403',
          '404',
          '500',
          'forbidden',
          'unauthorized',
          'csrf',
          'sqlstate',
          'exception',
          'stack trace',
        ];

        if (href && /\/admin\/login(?:[/?#]|$)/i.test(href)) {
          fail('La URL redirigió al login de admin. Abre el panel completo para revisar sesión/guard.');
          return;
        }

        if (loginHints.some(x => titleText.includes(x) || bodyText.includes(x))) {
          fail('La respuesta parece ser una pantalla de login o autenticación, no el panel embebido.');
          return;
        }

        if (errorHints.some(x => titleText.includes(x) || bodyText.includes(x))) {
          fail('La página respondió con un error o mensaje técnico. Ábrela en pestaña para ver el detalle real.');
          return;
        }

        success();
      } catch (_) {
        // Si no podemos inspeccionarlo, asumimos que al menos cargó.
        success();
      }
    };

    frame.onload = () => {
      setTimeout(inspectFrame, 120);
    };

    frame.onerror = () => {
      fail('El iframe no pudo cargar el recurso solicitado.');
    };

    setTimeout(() => {
      if (!frame || frame.dataset.fbToken !== token) return;
      frame.src = url;
    }, 30);

    setTimeout(() => {
      if (!frame || frame.dataset.fbToken !== token) return;
      if (wrap.dataset.state === 'loading') {
        fail('El panel tardó demasiado en responder o no pudo renderizarse dentro del modal.');
      }
    }, 3500);
  };

  const closeAcIframeModal = () => {
    const modal = $('#acIframeModal');
    const wrap = $('#acIf_wrap');
    const frame = $('#acIf_frame');
    const fb = $('#acIf_fallback');
    const fbUrl = $('#acIf_fallback_url');
    const fbReason = $('#acIf_fallback_reason');
    const status = $('#acIf_status');
    const statusText = $('#acIf_status_text');

    if (!modal) return;

    closeModal(modal);

    if (frame) {
      frame.onload = null;
      frame.onerror = null;
      frame.dataset.fbToken = '';
      frame.dataset.currentUrl = '';
      frame.src = 'about:blank';
    }

    if (wrap) wrap.dataset.state = 'idle';
    if (fb) fb.hidden = true;
    if (fbUrl) fbUrl.textContent = '—';
    if (fbReason) {
      fbReason.textContent = 'La página puede haber redirigido al login, cargado un layout no embebible o haber respondido con un error.';
    }

    if (status) {
      status.classList.remove('is-loading', 'is-ready', 'is-error');
    }
    if (statusText) statusText.textContent = 'Cargando panel…';
  };

  // =========================================================
  // DEBUG (solo console; sin overlay)
  // =========================================================
  const DBG = (() => {
    const qs = new URLSearchParams(location.search || '');
    const enabled = qs.get('acdbg') === '1' || String(localStorage.getItem('acdbg') || '') === '1';
    const log = (...args) => { if (enabled) { try { console.log('[AC]', ...args); } catch (_) {} } };
    log('loaded', VERSION);
    return { enabled, log };
  })();

  const defaultPeriod = page.getAttribute('data-default-period') || '';

  // =========================================================
  // Scroll lock (drawer/modales)
  // =========================================================
  const Lock = {
    _count: 0,
    on() {
      this._count = Math.max(0, this._count + 1);
      document.documentElement.classList.add('ac-lock');
      document.body.classList.add('ac-lock');
      DBG.log('Lock.on', { count: this._count });
    },
    off() {
      this._count = Math.max(0, this._count - 1);
      if (this._count === 0) {
        document.documentElement.classList.remove('ac-lock');
        document.body.classList.remove('ac-lock');
      }
      DBG.log('Lock.off', { count: this._count });
    },
    reset() {
      this._count = 0;
      document.documentElement.classList.remove('ac-lock');
      document.body.classList.remove('ac-lock');
      DBG.log('Lock.reset');
    }
  };

  // =========================================================
  // Sticky vars (para head fijo)
  // =========================================================
  const updateStickyVars = (() => {
    let raf = 0;
    const run = () => {
      raf = 0;
      const root = document.documentElement;

      const topbar =
        document.querySelector('.ac-topbar') ||
        document.querySelector('.layout-navbar, .navbar, header.navbar, header, .topbar, .app-header, .main-header');

      if (!topbar) {
        root.style.setProperty('--ac-sticky-top', '8px');
        root.style.setProperty('--ac-topbar-h', '0px');
        return;
      }

      const r = topbar.getBoundingClientRect();
      const gap = 8;
      const stickyTop = Math.max(0, Math.round(r.bottom + gap));
      const topbarH = Math.max(0, Math.round(r.height));

      root.style.setProperty('--ac-sticky-top', `${stickyTop}px`);
      root.style.setProperty('--ac-topbar-h', `${topbarH}px`);
    };

    return () => {
      if (raf) return;
      raf = requestAnimationFrame(run);
    };
  })();

  updateStickyVars();
  window.addEventListener('load', updateStickyVars, { passive: true });
  window.addEventListener('resize', updateStickyVars, { passive: true });
  window.addEventListener('scroll', updateStickyVars, { passive: true });

  // =========================================================
  // Double scroll fix (solo si NO hay lock)
  // =========================================================
  const DoubleScrollFix = (() => {
    const isScrollable = (el) => {
      if (!el || el === document.documentElement || el === document.body) return false;
      const cs = getComputedStyle(el);
      const oy = (cs.overflowY || '').toLowerCase();
      const canScroll = (oy === 'auto' || oy === 'scroll' || oy === 'overlay');
      if (!canScroll) return false;
      return (el.scrollHeight - el.clientHeight) > 2;
    };

    const findScrollParent = (start) => {
      let el = start?.parentElement || null;
      while (el && el !== document.body && el !== document.documentElement) {
        if (isScrollable(el)) return el;
        el = el.parentElement;
      }
      return null;
    };

    const apply = () => {
      if (document.documentElement.classList.contains('ac-lock')) return;

      // scroll del viewport
      try {
        document.documentElement.style.overflowY = 'auto';
        document.body.style.overflowY = 'auto';
        document.documentElement.style.height = 'auto';
        document.body.style.height = 'auto';
      } catch (_) {}

      const sp = findScrollParent(page);
      if (!sp) return;

      sp.style.overflowY = 'visible';
      sp.style.overflowX = 'visible';
      sp.style.height = 'auto';
      sp.style.minHeight = '0';
      sp.style.maxHeight = 'none';

      try {
        const cs = getComputedStyle(sp);
        if ((cs.position || '').toLowerCase() === 'fixed') sp.style.position = 'relative';
      } catch (_) {}
    };

    let raf = 0;
    const run = () => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        apply();
      });
    };

    return { run, apply };
  })();

  DoubleScrollFix.run();
  window.addEventListener('load', DoubleScrollFix.run, { passive: true });
  window.addEventListener('resize', DoubleScrollFix.run, { passive: true });
  setTimeout(DoubleScrollFix.run, 80);

  // =========================================================
  // Helpers UI
  // =========================================================
  const setText = (selOrEl, val) => {
    const el = typeof selOrEl === 'string' ? $(selOrEl) : selOrEl;
    if (!el) return;
    const t = (val ?? '').toString().trim();
    el.textContent = t || '—';
  };

  const setHref = (selOrEl, href) => {
    const el = typeof selOrEl === 'string' ? $(selOrEl) : selOrEl;
    if (!el) return;
    const isA = (el.tagName || '').toLowerCase() === 'a';
    if (!href) {
      if (isA) el.setAttribute('href', '#');
      el.classList.add('is-disabled');
      el.setAttribute('aria-disabled', 'true');
      return;
    }
    el.classList.remove('is-disabled');
    el.removeAttribute('aria-disabled');
    if (isA) el.setAttribute('href', href);
  };

  const setAction = (selOrEl, action) => {
    const f = typeof selOrEl === 'string' ? $(selOrEl) : selOrEl;
    if (!f) return;
    f.setAttribute('action', action || '#');
    if (!action || action === '#') f.classList.add('is-disabled');
    else f.classList.remove('is-disabled');
  };

  const setBadge = (el, tone, label) => {
    if (!el) return;
    el.classList.remove('ok', 'warn', 'bad', 'primary', 'neutral');
    el.classList.add(tone || 'neutral');
    el.innerHTML = `<span class="dot"></span>${label || '—'}`;
  };

  const decodeHtml = (s) => {
    if (!s || typeof s !== 'string') return s;
    if (s.indexOf('&quot;') === -1 && s.indexOf('&#') === -1 && s.indexOf('&amp;') === -1) return s;
    const t = document.createElement('textarea');
    t.innerHTML = s;
    return t.value;
  };

  const parseClientFromRow = (row) => {
    if (!row) return null;
    try {
      let raw = row.getAttribute('data-client') || '';
      if (!raw) return null;
      raw = decodeHtml(raw);
      const obj = JSON.parse(raw);
      return (obj && typeof obj === 'object') ? obj : null;
    } catch (e) {
      DBG.log('parseClientFromRow error', e);
      return null;
    }
  };

  const enc = (v) => encodeURIComponent((v ?? '').toString());
  const guessRouteId = (client) => {
    if (!client) return '';
    const id = (client.id ?? '').toString().trim();
    if (id && /^\d+$/.test(id)) return id; // preferir id numérico
    const rfc = (client.rfc ?? '').toString().trim();
    return rfc || id || '';
  };

  const buildFallbackUrl = (client, suffix) => {
    const key = (client.key || guessRouteId(client) || '').toString().trim();
    if (!key) return '';
    return `/admin/clientes/${enc(key)}/${suffix}`;
  };

  // =========================================================
  // current client (1 solo mecanismo)
  // =========================================================
  const drawer = $('#clientDrawer');
  let CURRENT = null;

  const setCurrentClient = (client) => {
    if (!client || typeof client !== 'object') return false;
    CURRENT = client;
    if (drawer) drawer._client = client;
    window.P360_AC_CURRENT = client;
    return true;
  };

  const getCurrentClient = () => {
    if (drawer && drawer._client) return drawer._client;
    if (CURRENT) return CURRENT;
    if (window.P360_AC_CURRENT) return window.P360_AC_CURRENT;
    return null;
  };

  const findRowFromEventTarget = (target) => {
    if (!target) return null;
    const row = target.closest && target.closest('.ac-row[data-client]');
    if (row) return row;

    const wrap = target.closest && (
      target.closest('.cell.actions') ||
      target.closest('.ac-menu') ||
      target.closest('[data-open-drawer]') ||
      target.closest('[data-drawer-action]') ||
      target.closest('[data-menu-toggle]') ||
      target.closest('.ac-btn')
    );
    if (wrap) {
      const r2 = wrap.closest && wrap.closest('.ac-row[data-client]');
      if (r2) return r2;
    }
    return null;
  };

  // =========================================================
  // Drawer open/close
  // =========================================================
  const openDrawer = (client) => {
    if (!drawer || !client) return;

    updateStickyVars();

    setText('#dr_rfc', client.rfc);
    setText('#dr_rs', client.razon_social);
    setText('#dr_meta', `Creado: ${client.created || '—'}`);

    setText('#dr_plan', (client.plan || '—').toString().toUpperCase());
    setText('#dr_cycle', client.billing_cycle_label || client.billing_cycle || '—');
    setText('#dr_next', client.next_invoice_label || client.next_invoice_date || '—');

    const amt = client.effective_amount_mxn || client.custom_amount_mxn || '';
    setText('#dr_amount', amt ? `$${Number(amt).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—');

    setText('#dr_email', client.email || '—');
    setText('#dr_phone', client.phone || '—');

    setBadge($('#dr_badge_block'), client.blocked ? 'bad' : 'ok', client.blocked ? 'Bloqueado' : 'Operando');
    setBadge($('#dr_badge_mail'), client.mail_ok ? 'ok' : 'warn', client.mail_ok ? 'Correo ✔' : 'Correo pendiente');
    setBadge($('#dr_badge_phone'), client.phone_ok ? 'ok' : 'warn', client.phone_ok ? 'Tel ✔' : 'Tel pendiente');

    const stmtList = (client.recips_statement || '').trim();
    const stmtCount = stmtList ? stmtList.split(',').map(s => s.trim()).filter(Boolean).length : 0;
    const firstStmt = stmtList ? (stmtList.split(',')[0] || '').trim() : '';
    setText('#dr_stmt_main', stmtCount ? (client.primary_statement || firstStmt || '—') : 'Sin correos');
    setText('#dr_stmt_list', stmtCount ? `${stmtList}${stmtCount ? ` (${stmtCount})` : ''}` : 'Sin destinatarios configurados');

    setAction('#drFormImpersonate', buildFallbackUrl(client, 'impersonate'));
    setAction('#drFormResetPass', buildFallbackUrl(client, 'reset-password'));

    const credsUrl = (client.email_creds_url || '').toString().trim();
    setAction('#drFormEmailCreds', credsUrl || buildFallbackUrl(client, 'email-credentials'));

    setCurrentClient(client);

    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    Lock.on();
  };

  const closeDrawer = () => {
    if (!drawer) return;
    if (!drawer.classList.contains('open')) return;

    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    drawer._client = null;

    Lock.off();
    updateStickyVars();
  };

  // =========================================================
  // Modales open/close
  // =========================================================
  const openModal = (sel) => {
    const m = typeof sel === 'string' ? $(sel) : sel;
    if (!m) return;
    if (m.classList.contains('open') || m.classList.contains('show')) return;

    m.classList.add('open', 'show');
    m.setAttribute('aria-hidden', 'false');
    Lock.on();
  };

  const closeModal = (m) => {
    if (!m) return;
    if (!m.classList.contains('open') && !m.classList.contains('show')) return;

    m.classList.remove('open', 'show');
    m.setAttribute('aria-hidden', 'true');
    Lock.off();
    updateStickyVars();
  };

  // =========================================================
  // Fill modales
  // =========================================================
  const fillEditModal = (client) => {
    if (!client) return;

    const key = guessRouteId(client); // RFC o ID (preferimos numérico si existe)
    const rs = (client.razon_social || '').toString().trim();
    const rfc = (client.rfc || client.id || '').toString().trim();

    setText('#mEdit_sub', `${rfc || '—'} · ${rs || '—'}`);

    // ✅ Mostrar RFC/ID en readonly (si existe el input en el Blade)
    const keyShow = $('#mEdit_key_show');
    if (keyShow) keyShow.value = (rfc || key || '').toString();

    const form = $('#mEdit_form');
    if (form) {
      const tpl = (form.getAttribute('data-save-template') || '').toString();
      // data-save-template trae algo como: /admin/clientes/__KEY__/save
      const action =
        (tpl && tpl.includes('__KEY__') && key) ? tpl.replace('__KEY__', encodeURIComponent(key))
        : buildFallbackUrl(client, 'save');

      setAction(form, action);
    }

    // ✅ Hidden id debe coincidir con el KEY real que usa el action
    const hid = $('#mEdit_id');
    if (hid) hid.value = (key || '').toString();

    const setVal = (sel, v) => {
      const el = $(sel);
      if (el) el.value = (v ?? '').toString();
    };

    setVal('#mEdit_rs', rs);
    setVal('#mEdit_email', client.email || '');
    setVal('#mEdit_phone', client.phone || client.telefono || '');
    setVal('#mEdit_plan', (client.plan || '').toString().toLowerCase());
    setVal('#mEdit_cycle', client.billing_cycle || '');
    setVal('#mEdit_next', client.next_invoice_date || '');

    // ✅ IMPORTANTE: el campo es "custom_amount_mxn". NO metas el effective.
    setVal('#mEdit_custom', client.custom_amount_mxn || '');

    const chkBlocked = $('#mEdit_blocked');
    if (chkBlocked) chkBlocked.checked = !!client.blocked;
  };

    const fillRecipientsModal = (client) => {
        if (!client) return;

        setText('#mRec_sub', `${client.rfc} · ${client.razon_social || '—'}`);

        const missing = $('#mRec_missing');
        const hasRoute = !!(client.recip_url && String(client.recip_url).trim());
        if (missing) missing.hidden = hasRoute;

        const recipUrl = hasRoute ? String(client.recip_url).trim() : '';
        setAction('#mRec_form_statement', recipUrl || '#');
        setAction('#mRec_form_invoice', recipUrl || '#');
        setAction('#mRec_form_general', recipUrl || '#');

        const st = $('#mRec_stmt_list'); if (st) st.value = client.recips_statement || '';
        const sp = $('#mRec_stmt_primary'); if (sp) sp.value = client.primary_statement || '';

        const il = $('#mRec_inv_list'); if (il) il.value = client.recips_invoice || '';
        const ip = $('#mRec_inv_primary'); if (ip) ip.value = client.primary_invoice || '';

        const gl = $('#mRec_gen_list'); if (gl) gl.value = client.recips_general || '';
        const gp = $('#mRec_gen_primary'); if (gp) gp.value = client.primary_general || '';

        // Reset visual de tabs al abrir el modal
        const tabsRoot = $('#modalRecipients [data-tabs]');
        if (tabsRoot) {
          tabsRoot.querySelectorAll('.ac-tab').forEach((b) => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
          });

          tabsRoot.querySelectorAll('.ac-tabpane').forEach((p) => {
            p.classList.remove('show');
            p.setAttribute('hidden', 'hidden');
          });

          const firstBtn = tabsRoot.querySelector('.ac-tab[data-tab="tabRecStmt"]');
          const firstPane = $('#tabRecStmt');

          if (firstBtn) {
            firstBtn.classList.add('active');
            firstBtn.setAttribute('aria-selected', 'true');
          }

          if (firstPane) {
            firstPane.classList.add('show');
            firstPane.removeAttribute('hidden');
          }
        }
      };

  const fillCredsModal = (client) => {
    if (!client) return;

    setText('#mCred_sub', `${client.rfc} · ${client.razon_social || '—'}`);
    setText('#mCred_rfc', client.rfc);
    setText('#mCred_owner', client.owner_email || client.email || '—');
    setText('#mCred_pass', client.temp_pass || '—');

    // Mostrar/ocultar contraseña visual
    const passEl = $('#mCred_pass');
    if (passEl) {
      const v = (client.temp_pass || '').toString().trim();
      passEl.classList.toggle('is-empty', !v);
    }

    const otp = client.otp_code ? `${client.otp_code} (${(client.otp_channel || '—').toUpperCase()})` : '—';
    setText('#mCred_otp', otp);

    const tok = (client.token_url || '').trim();
    const tokActions = $('#mCred_tok_actions');

    if (tok) {
      setText('#mCred_tok', tok);
      setText('#mCred_tok_exp', client.token_expires ? `Expira: ${client.token_expires}` : 'Expira: —');
      if (tokActions) tokActions.hidden = false;
      const a = $('#mCred_tok_open');
      if (a) a.setAttribute('href', tok);
    } else {
      setText('#mCred_tok', client.email_token ? client.email_token : 'Sin token vigente.');
      setText('#mCred_tok_exp', client.token_expires ? `Expira: ${client.token_expires}` : '—');
      if (tokActions) tokActions.hidden = true;
    }

    setAction('#mCred_form_force_email', buildFallbackUrl(client, 'force-email'));
    setAction('#mCred_form_force_phone', buildFallbackUrl(client, 'force-phone'));

    const emailForm = $('#mCred_form_email_creds');
    const emailMissing = $('#mCred_email_creds_missing');

    const credsUrl = (client.email_creds_url || '').toString().trim();
    const actionUrl = credsUrl || buildFallbackUrl(client, 'email-credentials');
    if (emailForm) {
      setAction(emailForm, actionUrl);
      if (emailMissing) emailMissing.hidden = !!credsUrl;
    } else {
      if (emailMissing) emailMissing.hidden = false;
    }

    const to = $('#mCred_to');
    if (to) {
      const csv = (client.recips_statement || '').toString().trim();
      const fallback = (client.email || '').toString().trim();
      const nextVal = (csv || fallback || '').trim();

      const cid = (client.id || client.rfc || '').toString();
      const prev = (to.getAttribute('data-last-client') || '').toString();
      if (cid && cid !== prev) {
        to.value = nextVal;
        to.setAttribute('data-last-client', cid);
      } else {
        if (!to.value.trim()) to.value = nextVal;
        if (cid) to.setAttribute('data-last-client', cid);
      }
    }

    const user = (client.owner_email || client.email || client.rfc || client.id || '').toString();
    const pass = (client.temp_pass || client.otp_code || '').toString();
    const access = (client.access_url || client.token_url || '').toString();

    const hu = $('#mCred_hidden_user'); if (hu) hu.value = user;
    const hp = $('#mCred_hidden_pass'); if (hp) hp.value = pass;
    const ha = $('#mCred_hidden_access'); if (ha) ha.value = access;
    const hr = $('#mCred_hidden_rfc'); if (hr) hr.value = (client.rfc || '').toString();
    const hrs = $('#mCred_hidden_rs'); if (hrs) hrs.value = (client.razon_social || '').toString();
  };



  const fillBillingModal = (client) => {
    if (!client) return;

    setText('#mBill_sub', `${client.rfc} · ${client.razon_social || '—'}`);
    setText('#mBill_period', client.default_period || defaultPeriod || '—');

    const per = client.default_period || defaultPeriod || '';

    const seedMissing = $('#mBill_seed_missing');
    const showMissing = $('#mBill_show_missing');
    const emailMissing = $('#mBill_email_missing');

    if (client.seed_url) {
      setAction('#mBill_form_seed', client.seed_url);
      if (seedMissing) seedMissing.hidden = true;
    } else {
      setAction('#mBill_form_seed', '#');
      if (seedMissing) seedMissing.hidden = false;
    }

    const seedPer = $('#mBill_seed_period');
    if (seedPer) seedPer.value = per;

    if (client.stmt_show_url) {
      setHref('#mBill_btn_show', client.stmt_show_url);
      if (showMissing) showMissing.hidden = true;
    } else {
      setHref('#mBill_btn_show', '');
      if (showMissing) showMissing.hidden = false;
    }

    if (client.stmt_email_url) {
      setAction('#mBill_form_email', client.stmt_email_url);
      if (emailMissing) emailMissing.hidden = true;
    } else {
      setAction('#mBill_form_email', '#');
      if (emailMissing) emailMissing.hidden = false;
    }
  };

  const openDrawerModalAction = (action, client) => {
    if (!client) return false;

    setCurrentClient(client);

    if (action === 'edit') {
      fillEditModal(client);
      DBG.log('open edit modal', {
        key: guessRouteId(client),
        action: $('#mEdit_form')?.getAttribute('action'),
        id: $('#mEdit_id')?.value
      });
      openModal('#modalEdit');
      return true;
    }

    if (action === 'recipients') {
      fillRecipientsModal(client);
      openModal('#modalRecipients');
      return true;
    }

    if (action === 'creds') {
      fillCredsModal(client);
      openModal('#modalCreds');
      return true;
    }

    if (action === 'billing') {
      fillBillingModal(client);
      openModal('#modalBilling');
      return true;
    }

    return false;
  };

  // =========================================================
  // Tabs
  // =========================================================
  const handleTabs = (tabBtn) => {
    const tabs = tabBtn.closest('[data-tabs]');
    if (!tabs) return;

    tabs.querySelectorAll('.ac-tab').forEach((b) => {
      b.classList.remove('active');
      b.setAttribute('aria-selected', 'false');
    });
    tabBtn.classList.add('active');
    tabBtn.setAttribute('aria-selected', 'true');

    const targetId = tabBtn.getAttribute('data-tab');
    tabs.querySelectorAll('.ac-tabpane').forEach((p) => {
      p.classList.remove('show');
      p.setAttribute('hidden', 'hidden');
    });
    const pane = targetId ? document.getElementById(targetId) : null;
    if (pane) {
      pane.classList.add('show');
      pane.removeAttribute('hidden');
    }
  };

  // =========================================================
  // Copy
  // =========================================================
  const handleCopy = async (btn) => {
    const sel = (btn.getAttribute('data-copy') || '').trim();
    if (!sel) return;

    const target = $(sel);
    const text = (() => {
      if (!target) return '';
      if ('value' in target) return String(target.value || '');
      return String(target.textContent || '');
    })().trim();

    if (!text) return;

    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        ta.style.top = '-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        document.execCommand('copy');
        ta.remove();
      }
      btn.classList.add('is-copied');
      setTimeout(() => btn.classList.remove('is-copied'), 900);
    } catch (_) {}
  };

  // =========================================================
  // Menú ⋯ (toggle)
  // =========================================================
  const closeAllMenusExcept = (keep) => {
    $$('.ac-menu.open,[data-menu].open').forEach(m => { if (m !== keep) m.classList.remove('open'); });
  };

  // =========================================================
  // QuickSearch: ESC limpia
  // =========================================================
  const qf = $('#quickSearchForm');
  if (qf) {
    const q = qf.querySelector('input[name="q"]');
    if (q) {
      q.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          q.value = '';
          qf.submit();
        }
      });
    }
  }

  // Filtros: autosubmit selects
  const filtersForm = $('#filtersForm');
  if (filtersForm) {
    filtersForm.querySelectorAll('select').forEach((sel) =>
      sel.addEventListener('change', () => filtersForm.submit())
    );
  }

  // =========================================================
  // ÚNICO handler global
  // =========================================================
  document.addEventListener('click', (e) => {
    const t = e.target;

    // 1) Captura y setea current client si aplica (fila tocada)
    const row = findRowFromEventTarget(t);
    if (row) {
      const c = parseClientFromRow(row);
      if (c) setCurrentClient(c);
    }

    // 2) Menu action (block/unblock/etc. legacy) -> abrimos drawer
    const menuAction = t.closest('[data-menu] [data-action]');
    if (menuAction) {
      e.preventDefault();

      const action = (menuAction.getAttribute('data-action') || '').trim();
      const row2 = menuAction.closest('.ac-row[data-client]');
      const client = parseClientFromRow(row2) || getCurrentClient();

      const menu = menuAction.closest('[data-menu]');
      if (menu) menu.classList.remove('open');

      if (!client) return;

      openDrawer(client);

      if (action === 'edit') { openDrawerModalAction('edit', client); return; }
      if (action === 'recipients') { openDrawerModalAction('recipients', client); return; }
      if (action === 'creds') { openDrawerModalAction('creds', client); return; }
      if (action === 'billing') { openDrawerModalAction('billing', client); return; }

      // acciones core se quedan como forms del drawer (ya las tienes)
      return;
    }

    // 2.5) Acciones de fila/menú que abren modales (edit/recipients/creds/billing)
    const rowDrawerAction = t.closest('[data-drawer-action]');
    if (rowDrawerAction && !t.closest('#clientDrawer')) {
      e.preventDefault();

      const action = (rowDrawerAction.getAttribute('data-drawer-action') || '').trim().toLowerCase();
      const row2 = rowDrawerAction.closest('.ac-row[data-client]');
      const client = parseClientFromRow(row2) || getCurrentClient();

      const menu = rowDrawerAction.closest('[data-menu]');
      if (menu) menu.classList.remove('open');

      if (!client || !action) return;

      openDrawerModalAction(action, client);
      return;
    }

    // 3) Menu toggle
    const menuToggle = t.closest('[data-menu-toggle]');
    if (menuToggle) {
      e.preventDefault();
      const menu = menuToggle.closest('[data-menu]');
      if (!menu) return;
      closeAllMenusExcept(menu);
      menu.classList.toggle('open');
      return;
    } else {
      const insideMenu = t.closest('[data-menu]');
      if (!insideMenu) closeAllMenusExcept(null);
    }

    // 4) data-open-modal
    const openModalBtn = t.closest('[data-open-modal]');
    if (openModalBtn) {
      e.preventDefault();
      const sel = (openModalBtn.getAttribute('data-open-modal') || '').trim();
      if (sel) openModal(sel);
      return;
    }

     // 4.6) Export CSV
    if (t.closest && t.closest('#btnExportCsv')) {
      e.preventDefault();
      handleExportCsv();
      return;
    }

    // 4.7) Cerrar iframe modal
    if (t.closest('[data-close-ac-iframe]')) {
      e.preventDefault();
      closeAcIframeModal();
      return;
    }

    // 4.75) Reintentar iframe modal
    const retryIf = t.closest('#acIf_retry');
    if (retryIf) {
      e.preventDefault();

      const c = getCurrentClient();
      const url = String(retryIf.getAttribute('data-url') || '').trim();

      if (!url || !c) return;

      const rs = (c.razon_social || '').toString().trim();
      const rfc = (c.rfc || c.id || '').toString().trim();
      const currentTitle = ($('#acIf_title')?.textContent || 'Submódulo').trim();

      openAcIframeModal({
        title: currentTitle,
        subtitle: (rs ? (rs + ' · ') : '') + (rfc ? ('RFC/ID ' + rfc) : ''),
        url
      });
      return;
    }

    // 4.8) Abrir iframe modal (admin/state)
    const openIf = t.closest('[data-open-iframe]');
    if (openIf) {
      e.preventDefault();

      const mode = String(openIf.getAttribute('data-open-iframe') || '').trim();
      const c = getCurrentClient();
      if (!c) {
        alert('No se detectó el cliente actual.');
        return;
      }

      const rs = (c.razon_social || '').toString().trim();
      const rfc = (c.rfc || c.id || '').toString().trim();

      if (mode === 'admin') {
        const url = (c.billing_admin_url || '').toString().trim();
        openAcIframeModal({
          title: 'Administrar (Billing)',
          subtitle: (rs ? (rs + ' · ') : '') + (rfc ? ('RFC/ID ' + rfc) : ''),
          url
        });
        return;
      }

      if (mode === 'state') {
        const url = (c.billing_statehub_url || '').toString().trim();
        openAcIframeModal({
          title: 'Estado (Hub)',
          subtitle: (rs ? (rs + ' · ') : '') + (rfc ? ('RFC/ID ' + rfc) : ''),
          url
        });
        return;
      }
    }

    // 5) Abrir drawer
    const openBtn = t.closest('[data-open-drawer]');
    if (openBtn) {
      const row3 = openBtn.closest('.ac-row[data-client]');
      const client = parseClientFromRow(row3) || getCurrentClient();
      if (client) openDrawer(client);
      return;
    }

    // 6) Drawer: acciones -> abrir modales
    if (drawer && drawer.classList.contains('open') && t.closest('#clientDrawer')) {
     const btn = t.closest('[data-drawer-action], #btnOpenEdit, #btnOpenRecipients, #btnOpenCreds, #btnOpenBilling, #drFormEmailCreds button');
      if (btn) {
        e.preventDefault();
        const c = getCurrentClient();
        if (!c) return;

        const da = (btn.getAttribute('data-drawer-action') || '').trim().toLowerCase();
        const id = (btn.getAttribute('id') || '').trim();

        const action =
          da ||
          (id === 'btnOpenEdit' ? 'edit' :
           id === 'btnOpenRecipients' ? 'recipients' :
           id === 'btnOpenCreds' ? 'creds' :
           id === 'btnOpenBilling' ? 'billing' :
           (btn.closest && btn.closest('#drFormEmailCreds') ? 'creds' : '')
          );

        if (action) openDrawerModalAction(action, c);
        return;
      }
    }

    // 7) Cerrar drawer
    if (t.closest('[data-close-drawer]')) {
      closeDrawer();
      return;
    }

    // 8) Tabs
    const tabBtn = t.closest('.ac-tab[data-tab]');
    if (tabBtn) {
      e.preventDefault();
      handleTabs(tabBtn);
      return;
    }

    // 9) Copy
    const copyBtn = t.closest('[data-copy]');
    if (copyBtn) {
      e.preventDefault();
      handleCopy(copyBtn);
      return;
    }

    // 10) Close modal
    if (t.closest('[data-close-modal]')) {
      const m = t.closest('.ac-modal');
      closeModal(m);
      return;
    }

    // 11) Backdrop click -> close modal (si aplica)
    const modal = t.closest('.ac-modal.open, .ac-modal.show');
    if (modal) {
      const isBackdrop = t === modal;
      const clickedInside = !!t.closest('.ac-modal-card,.ac-modal__card,.modal-card,.modal-content');
      if (isBackdrop && !clickedInside) {
        closeModal(modal);
        return;
      }
    }

    // 12) asegurar no vuelva scroll interno
    DoubleScrollFix.run();
  }, true);

  // =========================================================
  // ESC
  // =========================================================
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const anyMenu = $('.ac-menu.open,[data-menu].open');
    if (anyMenu) {
      closeAllMenusExcept(null);
      return;
    }

    const openModalEl = $('.ac-modal.open') || $('.ac-modal.show');
    if (openModalEl) {
      closeModal(openModalEl);
      return;
    }

    if (drawer && drawer.classList.contains('open')) {
      closeDrawer();
    }
  });

  // =========================================================
  // Normalize initial lock state
  // =========================================================
  (function normalizeInitialState() {
    const anyModalOpen = !!($('.ac-modal.open') || $('.ac-modal.show'));
    const drawerOpen = !!(drawer && drawer.classList.contains('open'));

    if (anyModalOpen || drawerOpen) {
      Lock.reset();
      if (drawerOpen) Lock.on();
      if (anyModalOpen) Lock.on();
      document.documentElement.classList.add('ac-lock');
      document.body.classList.add('ac-lock');
    } else {
      Lock.reset();
    }
  })();

})();