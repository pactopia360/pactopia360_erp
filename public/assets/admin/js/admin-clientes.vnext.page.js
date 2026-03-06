// public/assets/admin/js/admin-clientes.vnext.page.js
// Pactopia360 · Admin Clientes · vNext page bundle
// - Extrae JS inline del Blade (hooks creds, captura current client, edit modal save, export CSV, iframe modal)
// - Diseñado para coexistir con assets/admin/js/admin-clientes.js (legacy/base)

(function () {
  'use strict';

  // -------------------------
  // Utils
  // -------------------------
  const $ = (s, sc) => (sc || document).querySelector(s);

  function decodeHtmlEntities(s) {
    s = String(s || '');
    if (!s) return '';
    const t = document.createElement('textarea');
    t.innerHTML = s;
    return t.value;
  }

  function parseJsonMaybe(raw) {
    try { return JSON.parse(raw); } catch (e) { return null; }
  }

  function parseClientFromRow(row) {
    if (!row) return null;
    const raw = row.getAttribute('data-client') || '';
    if (!raw) return null;

    // data-client viene escapado por e() => &quot;...&quot;
    const json = decodeHtmlEntities(raw);
    const c = parseJsonMaybe(json);
    return (c && typeof c === 'object') ? c : null;
  }

  function setCurrentClient(c) {
    if (!c || typeof c !== 'object') return;
    const drawer = $('#clientDrawer');
    if (drawer) drawer._client = c;
    window.P360_AC_CURRENT = c; // fallback global
  }

  function currentClient() {
    const drawer = $('#clientDrawer');
    if (drawer && drawer._client) return drawer._client;
    if (window.P360_AC_CURRENT) return window.P360_AC_CURRENT;
    return null;
  }

  function findRowFromEventTarget(target) {
    if (!target) return null;

    // 1) Click dentro de una fila
    const row = target.closest('.ac-row[data-client]');
    if (row) return row;

    // 2) Click en acciones/botones dentro de la fila
    const wrap = target.closest('.cell.actions') || target.closest('.ac-menu') || target.closest('.ac-btn');
    if (wrap) {
      const r2 = wrap.closest('.ac-row[data-client]');
      if (r2) return r2;
    }

    // 3) fallback: primera fila
    return document.querySelector('.ac-row[data-client]');
  }

  // =====================================================
  // 1) CAPTURA TEMPRANA DEL CURRENT CLIENT (drawer._client)
  // =====================================================
  function installCurrentClientCapture() {
    // Captura “muy temprano” (capturing = true)
    document.addEventListener('click', function (e) {
      const row = findRowFromEventTarget(e.target);
      if (!row) return;
      const c = parseClientFromRow(row);
      if (c) setCurrentClient(c);
    }, true);

    document.addEventListener('focusin', function (e) {
      const row = findRowFromEventTarget(e.target);
      if (!row) return;
      const c = parseClientFromRow(row);
      if (c) setCurrentClient(c);
    }, true);

    // Helper global para debug
    window.__AC_SET_CURRENT_FROM_FIRST_ROW = function () {
      const row = document.querySelector('.ac-row[data-client]');
      const c = parseClientFromRow(row);
      if (c) setCurrentClient(c);
      return !!c;
    };
  }

  // =====================================================
  // 2) HOOK: Enviar credenciales (set action + defaults + payload)
  // =====================================================
  function normCsvEmails(csv) {
    csv = String(csv || '').trim();
    if (!csv) return '';
    const parts = csv.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
    const ok = [];
    const seen = new Set();
    for (const e of parts) {
      if (!seen.has(e) && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
        seen.add(e);
        ok.push(e);
      }
    }
    return ok.join(', ');
  }

  function ensureCredsEmailActionAndPayload() {
    const c = currentClient();

    const form = $('#mCred_form_email_creds');
    const missing = $('#mCred_email_creds_missing');
    if (!form) return;

    // action endpoint
    const url = c && c.email_creds_url ? String(c.email_creds_url) : '';
    if (!url || url === '#') {
      form.setAttribute('action', '#');
      if (missing) missing.hidden = false;
    } else {
      form.setAttribute('action', url);
      if (missing) missing.hidden = true;
    }

    // default to
    const to = $('#mCred_to');
    if (to) {
      const csv = c && c.recips_statement ? String(c.recips_statement || '') : '';
      const fallback = c && c.email ? String(c.email || '') : '';
      const nextVal = normCsvEmails(csv || fallback);

      const cid = c && c.id ? String(c.id) : '';
      const prev = String(to.getAttribute('data-last-client') || '');

      if (cid && cid !== prev) {
        to.value = nextVal;
        to.setAttribute('data-last-client', cid);
      } else {
        if (!to.value.trim()) to.value = nextVal;
        if (cid) to.setAttribute('data-last-client', cid);
      }
    }

    // payload hidden
    const user =
      (c && c.owner_email ? String(c.owner_email) : '') ||
      (c && c.email ? String(c.email) : '') ||
      (c && c.rfc ? String(c.rfc) : '') ||
      '';

    const pass =
      (c && c.temp_pass ? String(c.temp_pass) : '') ||
      (c && c.otp_code ? String(c.otp_code) : '') ||
      '';

    const access =
      (c && c.access_url ? String(c.access_url) : '') ||
      (c && c.token_url ? String(c.token_url) : '') ||
      '';

    const hu = $('#mCred_hidden_user'); if (hu) hu.value = user;
    const hp = $('#mCred_hidden_pass'); if (hp) hp.value = pass;
    const ha = $('#mCred_hidden_access'); if (ha) ha.value = access;

    const hr = $('#mCred_hidden_rfc'); if (hr) hr.value = (c && c.rfc ? String(c.rfc) : '');
    const hrs = $('#mCred_hidden_rs'); if (hrs) hrs.value = (c && c.razon_social ? String(c.razon_social) : '');
  }

  function installCredsHooks() {
    // Abre credenciales (desde drawer)
    document.addEventListener('click', function (e) {
      const btnCreds = e.target.closest('#btnOpenCreds');
      if (!btnCreds) return;
      setTimeout(ensureCredsEmailActionAndPayload, 80);
    });

    // “Enviar credenciales” del drawer: abre modal Credenciales
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('#drFormEmailCreds button');
      if (!btn) return;

      e.preventDefault();

      const openCreds = $('#btnOpenCreds');
      if (openCreds) openCreds.click();

      setTimeout(ensureCredsEmailActionAndPayload, 120);
    });

    // Mientras el modal está abierto, mantener payload consistente
    ['input', 'change', 'keyup'].forEach(evt => {
      document.addEventListener(evt, function () {
        const modal = $('#modalCreds');
        if (modal && modal.getAttribute('aria-hidden') === 'false') {
          ensureCredsEmailActionAndPayload();
        }
      }, true);
    });
  }

  // =====================================================
  // 3) EDIT MODAL: llenar action + submit via fetch
  // =====================================================
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function openModal(sel) {
    const m = $(sel);
    if (!m) return;
    m.setAttribute('aria-hidden', 'false');
    m.classList.add('show');
    document.documentElement.classList.add('ac-modal-open');
    document.body.classList.add('ac-modal-open');
  }

  function closeModal(sel) {
    const m = $(sel);
    if (!m) return;
    m.setAttribute('aria-hidden', 'true');
    m.classList.remove('show');
    document.documentElement.classList.remove('ac-modal-open');
    document.body.classList.remove('ac-modal-open');
  }

  function resolveKey(c) {
    if (!c) return '';
    const rfc = (c.rfc || '').toString().trim();
    if (rfc) return rfc;
    const id = (c.id || '').toString().trim();
    return id;
  }

  function resolveEditAction(form, key) {
    if (!form) return '';
    const tpl = (form.getAttribute('data-save-template') || '').toString();

    if (tpl && tpl.includes('__KEY__')) {
      return tpl.replaceAll('__KEY__', encodeURIComponent(key));
    }

    return `/admin/clientes/${encodeURIComponent(key)}/save`;
  }

  function setEditAction(form, key) {
    if (!form || !key) return false;
    form.setAttribute('action', resolveEditAction(form, key));
    return true;
  }

  function fillEditModalFromClient(c) {
    const form = $('#mEdit_form');
    if (!form || !c) return false;

    const key = resolveKey(c);
    if (!key) return false;

    const sub = $('#mEdit_sub');
    if (sub) sub.textContent = `${key} · ${c.razon_social || ''}`.trim();

    const hid = $('#mEdit_id'); if (hid) hid.value = key;
    const rs = $('#mEdit_rs'); if (rs) rs.value = (c.razon_social || '');
    const em = $('#mEdit_email'); if (em) em.value = (c.email || '');
    const ph = $('#mEdit_phone'); if (ph) ph.value = (c.phone || '');
    const pl = $('#mEdit_plan'); if (pl) pl.value = (c.plan || '');
    const cy = $('#mEdit_cycle'); if (cy) cy.value = (c.billing_cycle || '');
    const nx = $('#mEdit_next'); if (nx) nx.value = (c.next_invoice_date || '');
    const cu = $('#mEdit_custom'); if (cu) cu.value = (c.custom_amount_mxn || '');
    const bl = $('#mEdit_blocked'); if (bl) bl.checked = (String(c.blocked || '0') === '1');

    // siempre action real
    setEditAction(form, key);

    return true;
  }

  function ensureEditActionBeforeSubmit(form) {
    if (!form) return false;

    const action = (form.getAttribute('action') || '').trim();
    if (action && action !== '#') return true;

    const key = ($('#mEdit_id')?.value || '').toString().trim();
    if (key) return setEditAction(form, key);

    const c = currentClient();
    const k2 = resolveKey(c);
    if (k2) return setEditAction(form, k2);

    return false;
  }

  function installEditModalLogic() {
    function openEdit() {
      const c = currentClient();
      if (!c) {
        alert('No se detectó el cliente actual en el drawer.');
        return;
      }
      if (!fillEditModalFromClient(c)) {
        alert('No pude resolver la key (RFC/ID) para editar.');
        return;
      }
      openModal('#modalEdit');
    }

    // Click handlers
    document.addEventListener('click', function (e) {
      if (e.target.closest('#btnOpenEdit')) {
        e.preventDefault();
        openEdit();
        return;
      }

      const act = e.target.closest('[data-drawer-action="edit"]');
      if (act) {
        e.preventDefault();

        // setear current client desde la fila antes de abrir
        const row = act.closest('.ac-row[data-client]');
        if (row) {
          const c = parseClientFromRow(row);
          if (c) setCurrentClient(c);
        }

        setTimeout(openEdit, 30);
        return;
      }

      const close = e.target.closest('[data-close-modal]');
      if (close) {
        const modal = close.closest('.ac-modal');
        if (modal) {
          modal.setAttribute('aria-hidden', 'true');
          modal.classList.remove('show');
          document.documentElement.classList.remove('ac-modal-open');
          document.body.classList.remove('ac-modal-open');
        }
      }
    });

    // Observer: si otro JS abre modal y deja action '#', lo corregimos
    (function observeEditModal() {
      const modal = $('#modalEdit');
      const form = $('#mEdit_form');
      if (!modal || !form) return;

      const obs = new MutationObserver(() => {
        const shown = modal.classList.contains('show') || modal.getAttribute('aria-hidden') === 'false';
        if (!shown) return;

        setTimeout(() => {
          ensureEditActionBeforeSubmit(form);
        }, 30);
      });

      obs.observe(modal, { attributes: true, attributeFilter: ['class', 'aria-hidden'] });
    })();

    // Submit fetch
    document.addEventListener('submit', async function (e) {
      const form = e.target;
      if (!form || form.id !== 'mEdit_form') return;

      e.preventDefault();

      if (!ensureEditActionBeforeSubmit(form)) {
        alert('El formulario no tiene endpoint configurado (action). No se puede guardar.');
        return;
      }

      const action = (form.getAttribute('action') || '').trim();
      if (!action || action === '#') {
        alert('El formulario no tiene endpoint configurado (action).');
        return;
      }

      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.dataset.prevText = btn.textContent;
        btn.textContent = 'Guardando…';
      }

      try {
        const fd = new FormData(form);

        // checkbox unchecked => 0
        if (!fd.has('is_blocked')) fd.set('is_blocked', '0');

        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        const t = csrfToken();
        if (t) headers['X-CSRF-TOKEN'] = t;

        const res = await fetch(action, {
          method: 'POST',
          headers,
          body: fd,
          credentials: 'same-origin'
        });

        const ct = (res.headers.get('content-type') || '').toLowerCase();
        const payload = ct.includes('application/json') ? await res.json() : await res.text();

        if (!res.ok) {
          let msg = `Error al guardar (HTTP ${res.status}).`;

          if (res.status === 419) {
            msg = 'Sesión/CSRF expiró (419). Recarga la página e intenta de nuevo.';
          } else if (typeof payload === 'object' && payload) {
            msg = payload.message || msg;
            if (payload.errors) {
              const first = Object.values(payload.errors)[0];
              if (Array.isArray(first) && first[0]) msg = first[0];
            }
          } else if (typeof payload === 'string' && payload.trim()) {
            msg = msg + ' Revisa logs (laravel.log) para detalle.';
          }

          throw new Error(msg);
        }

        closeModal('#modalEdit');
        location.reload();

      } catch (err) {
        alert(err?.message || 'Error inesperado al guardar.');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = btn.dataset.prevText || 'Guardar';
        }
      }
    }, true);
  }

  // =====================================================
  // 4) EXPORT CSV (client-side)
  // =====================================================
  function safe(v) {
    if (v === null || v === undefined) return '';
    return String(v).replace(/\r?\n/g, ' ').trim();
  }

  function csvEscape(v) {
    v = safe(v);
    if (/[",\n]/.test(v)) return `"${v.replace(/"/g, '""')}"`;
    return v;
  }

  function getRowsData() {
    const rows = Array.from(document.querySelectorAll('.ac-row[data-client]'));
    const out = [];
    for (const row of rows) {
      const c = parseClientFromRow(row);
      if (c) out.push(c);
    }
    return out;
  }

  function buildCsv(data) {
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
  }

  function downloadCsv(csvText) {
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
    const page = (qp.get('page') || '').trim();

    const parts = [
      'clientes',
      stamp,
      plan ? `plan-${plan}` : null,
      blocked !== '' ? `blocked-${blocked}` : null,
      bs ? `billing-${bs}` : null,
      pp ? `pp-${pp}` : null,
      page ? `p-${page}` : null,
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
  }

  function installExportCsv() {
    function handleExport() {
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

        const csv = buildCsv(data);
        downloadCsv(csv);

      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = btn.dataset.prevText || 'Exportar';
        }
      }
    }

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('#btnExportCsv');
      if (!btn) return;
      e.preventDefault();
      handleExport();
    });
  }

  // =====================================================
  // 5) MODAL IFRAME (submódulos)
  // =====================================================
  function openAcIframeModal({ title, subtitle, url }) {
    const modal = $('#acIframeModal');
    const frame = $('#acIf_frame');
    const tEl = $('#acIf_title');
    const sEl = $('#acIf_sub');
    const openN = $('#acIf_open_new');

    const fb = $('#acIf_fallback');
    const fbUrl = $('#acIf_fallback_url');

    if (!modal || !frame) return;
    url = String(url || '').trim();

    if (!url || url === '#') {
      alert('No hay URL disponible para este submódulo (falta route o payload).');
      return;
    }

    if (tEl) tEl.textContent = title || '—';
    if (sEl) sEl.textContent = subtitle || '—';
    if (openN) openN.href = url;

    if (fb) fb.hidden = true;
    if (fbUrl) fbUrl.textContent = '—';

    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('show');
    document.documentElement.classList.add('ac-modal-open');
    document.body.classList.add('ac-modal-open');

    frame.src = url;

    const myToken = String(Date.now()) + Math.random();
    frame.dataset.fbToken = myToken;

    setTimeout(() => {
      if (!frame || frame.dataset.fbToken !== myToken) return;
      if (fb) {
        fb.hidden = false;
        if (fbUrl) fbUrl.textContent = url;
      }
    }, 1400);
  }

  function closeAcIframeModal() {
    const modal = $('#acIframeModal');
    const frame = $('#acIf_frame');
    const fb = $('#acIf_fallback');
    const fbUrl = $('#acIf_fallback_url');

    if (!modal) return;

    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('show');
    document.documentElement.classList.remove('ac-modal-open');
    document.body.classList.remove('ac-modal-open');

    if (frame) {
      frame.dataset.fbToken = '';
      frame.src = 'about:blank';
    }
    if (fb) fb.hidden = true;
    if (fbUrl) fbUrl.textContent = '—';
  }

  function installIframeModal() {
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-close-ac-iframe]')) {
        e.preventDefault();
        closeAcIframeModal();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        const modal = $('#acIframeModal');
        if (modal && modal.classList.contains('show')) closeAcIframeModal();
      }
    });

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-open-iframe]');
      if (!btn) return;

      const mode = String(btn.getAttribute('data-open-iframe') || '').trim();
      const c = currentClient();
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
        e.preventDefault();
        return;
      }

      if (mode === 'state') {
        const url = (c.billing_statehub_url || '').toString().trim();
        openAcIframeModal({
          title: 'Estado (Hub)',
          subtitle: (rs ? (rs + ' · ') : '') + (rfc ? ('RFC/ID ' + rfc) : ''),
          url
        });
        e.preventDefault();
      }
    }, true);

    const frame = $('#acIf_frame');
    frame && frame.addEventListener('load', function () {
      const fb = $('#acIf_fallback');
      if (fb) fb.hidden = true;
    });
  }

  // =====================================================
  // Boot
  // =====================================================
  installCurrentClientCapture();
  installCredsHooks();
  installEditModalLogic();
  installExportCsv();
  installIframeModal();

})();