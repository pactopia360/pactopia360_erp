/* public/assets/admin/js/admin-clientes.js
   Admin · Clientes — UI v13.2.x (lista + drawer + modales)
   FIX: usar rutas reales (email_credentials usa {rfc}), no hardcode por {id}
   + Robustez: action de forms desde payload (email_creds_url / recip_url / seed_url / stmt_*)
*/
(function () {
  'use strict';

  const $ = (s, sc) => (sc || document).querySelector(s);
  const $$ = (s, sc) => Array.from((sc || document).querySelectorAll(s));

  const page = $('#adminClientesPage');
  if (!page) return;

  const defaultPeriod = page.getAttribute('data-default-period') || '';

  // ===== Scroll lock manager (evita bug: cerrar modal desbloquea aunque drawer siga abierto)
  const Lock = {
    _count: 0,
    on() {
      this._count = Math.max(0, this._count + 1);
      document.documentElement.classList.add('ac-lock');
    },
    off() {
      this._count = Math.max(0, this._count - 1);
      if (this._count === 0) document.documentElement.classList.remove('ac-lock');
    },
    reset() {
      this._count = 0;
      document.documentElement.classList.remove('ac-lock');
    }
  };

  // QuickSearch: ESC limpia
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

  // ===== Helpers UI
  const setText = (id, val) => {
    const el = typeof id === 'string' ? $(id) : id;
    if (!el) return;
    const t = (val ?? '').toString().trim();
    el.textContent = t || '—';
  };

  const setHref = (id, href) => {
    const el = typeof id === 'string' ? $(id) : id;
    if (!el) return;

    const isAnchor = (el.tagName || '').toLowerCase() === 'a';

    if (!href) {
      if (isAnchor) el.setAttribute('href', '#');
      el.classList.add('is-disabled');
      el.setAttribute('aria-disabled', 'true');
      return;
    }
    el.classList.remove('is-disabled');
    el.removeAttribute('aria-disabled');
    if (isAnchor) el.setAttribute('href', href);
  };

  const setAction = (formId, action) => {
    const f = typeof formId === 'string' ? $(formId) : formId;
    if (!f) return;
    f.setAttribute('action', action || '#');
    if (!action) f.classList.add('is-disabled'); else f.classList.remove('is-disabled');
  };

  const setBadge = (el, tone, label) => {
    if (!el) return;
    el.classList.remove('ok', 'warn', 'bad', 'primary', 'neutral');
    el.classList.add(tone || 'neutral');
    el.innerHTML = `<span class="dot"></span>${label || '—'}`;
  };

  // decode html entities (por si el JSON viene escapado)
  const decodeHtml = (s) => {
    if (!s || typeof s !== 'string') return s;
    if (s.indexOf('&quot;') === -1 && s.indexOf('&#') === -1 && s.indexOf('&amp;') === -1) return s;
    const t = document.createElement('textarea');
    t.innerHTML = s;
    return t.value;
  };

  const parseClient = (row) => {
    if (!row) return null;
    try {
      let raw = row.getAttribute('data-client') || '{}';
      raw = decodeHtml(raw);
      const obj = JSON.parse(raw);
      return obj && typeof obj === 'object' ? obj : null;
    } catch (e) {
      return null;
    }
  };

  // helpers urls: el backend usa {rfc} en varias routes
  const enc = (v) => encodeURIComponent((v ?? '').toString());
  const guessRfcKey = (client) => {
    // preferimos rfc, luego id
    return (client && (client.rfc || client.id)) ? (client.rfc || client.id) : '';
  };

  const buildFallbackUrl = (client, suffix) => {
    // /admin/clientes/{rfc}/{suffix}
    const key = guessRfcKey(client);
    if (!key) return '';
    return `/admin/clientes/${enc(key)}/${suffix}`;
  };

  // ===== Drawer
  const drawer = $('#clientDrawer');

  const openDrawer = (client) => {
    if (!drawer || !client) return;

    setText('#dr_rfc', client.rfc);
    setText('#dr_rs', client.razon_social);
    setText('#dr_meta', `Creado: ${client.created || '—'}`);

    setText('#dr_plan', (client.plan || '—').toString().toUpperCase());
    setText('#dr_cycle', client.billing_cycle_label || client.billing_cycle || '—');
    setText('#dr_next', client.next_invoice_label || client.next_invoice_date || '—');

    const amt = client.effective_amount_mxn || client.custom_amount_mxn || '';
    setText(
      '#dr_amount',
      amt ? `$${Number(amt).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—'
    );

    // Contacto
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

    // Forms del drawer
    // Nota: impersonate/reset siguen usando {id} en tu backend (si es así). Si en tu backend también es {rfc}, cámbialo aquí.
    // Como en tu Blade usas rutas por {rfc}, preferimos usar rfc para clientes.
    setAction('#drFormImpersonate', buildFallbackUrl(client, 'impersonate'));      // /admin/clientes/{rfc}/impersonate
    setAction('#drFormResetPass', buildFallbackUrl(client, 'reset-password'));     // /admin/clientes/{rfc}/reset-password

    // ✅ FIX CLAVE: enviar credenciales debe usar email_creds_url desde payload (route real: admin.clientes.emailCredentials)
    const credsUrl = (client.email_creds_url || '').toString().trim();
    setAction('#drFormEmailCreds', credsUrl || buildFallbackUrl(client, 'email-credentials'));

    // stash
    drawer._client = client;

    // open
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
  };

  // ===== Modales
  const openModal = (id) => {
    const m = typeof id === 'string' ? $(id) : id;
    if (!m) return;
    if (m.classList.contains('open')) return;

    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    Lock.on();
  };

  const closeModal = (m) => {
    if (!m) return;
    if (!m.classList.contains('open')) return;

    m.classList.remove('open');
    m.setAttribute('aria-hidden', 'true');
    Lock.off();
  };

  // ===== Poblar modales
  const fillEditModal = (client) => {
    if (!client) return;
    setText('#mEdit_sub', `${client.rfc} · ${client.razon_social || '—'}`);
    setAction('#mEdit_form', buildFallbackUrl(client, 'save')); // /admin/clientes/{rfc}/save

    const rs = $('#mEdit_rs'); if (rs) rs.value = client.razon_social || '';
    const em = $('#mEdit_email'); if (em) em.value = client.email || '';
    const ph = $('#mEdit_phone'); if (ph) ph.value = client.phone || '';
    const pl = $('#mEdit_plan'); if (pl) pl.value = (client.plan || '').toLowerCase();
    const cy = $('#mEdit_cycle'); if (cy) cy.value = (client.billing_cycle || '').toLowerCase();
    const nx = $('#mEdit_next'); if (nx) nx.value = (client.next_invoice_date || '');
    const ca = $('#mEdit_custom'); if (ca) ca.value = client.custom_amount_mxn || '';
    const bl = $('#mEdit_blocked'); if (bl) bl.checked = !!client.blocked;
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
  };

  const fillCredsModal = (client) => {
    if (!client) return;

    setText('#mCred_sub', `${client.rfc} · ${client.razon_social || '—'}`);
    setText('#mCred_rfc', client.rfc);
    setText('#mCred_owner', client.owner_email || client.email || '—');
    setText('#mCred_pass', client.temp_pass || '—');

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
      if (tokActions) tokActions.hidden = !tok;
      // si solo hay token (no url), dejamos ocultas acciones
      if (!tok && tokActions) tokActions.hidden = true;
    }

    // force email / phone: en tus rutas existe force-email. phone puede existir o no.
    setAction('#mCred_form_force_email', buildFallbackUrl(client, 'force-email'));

        const forcePhoneUrl = buildFallbackUrl(client, 'force-phone');
    setAction('#mCred_form_force_phone', forcePhoneUrl);

    const missing = $('#mCred_force_phone_missing');
    if (missing) missing.hidden = true;

    // =========================================================
    // ✅ Enviar credenciales por correo (acción + defaults + payload)
    // =========================================================
    const emailForm = $('#mCred_form_email_creds');
    const emailMissing = $('#mCred_email_creds_missing');

    // Action (route real desde payload; si no, fallback)
    const credsUrl = (client.email_creds_url || '').toString().trim();
    const actionUrl = credsUrl || buildFallbackUrl(client, 'email-credentials');
    if (emailForm) {
      setAction(emailForm, credsUrl ? actionUrl : (credsUrl ? actionUrl : actionUrl)); // siempre setea, pero controla "missing"
      // Mostrar "missing" solo si NO viene ruta en payload (para que detectes si falta route real)
      if (emailMissing) emailMissing.hidden = !!credsUrl;
    } else {
      if (emailMissing) emailMissing.hidden = false;
    }

    // Defaults destinatarios (REFRESH por cambio de cliente)
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

    // Hidden payload
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

  // ===== Tabs
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

  // ===== Clipboard (fallback)
  const copyText = async (text) => {
    const t = (text || '').trim();
    if (!t) return false;

    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(t);
        return true;
      }
    } catch (e) {}

    try {
      const ta = document.createElement('textarea');
      ta.value = t;
      ta.setAttribute('readonly', 'readonly');
      ta.style.position = 'fixed';
      ta.style.top = '-1000px';
      ta.style.left = '-1000px';
      document.body.appendChild(ta);
      ta.select();
      const ok = document.execCommand('copy');
      ta.remove();
      return !!ok;
    } catch (e) {
      return false;
    }
  };

  const handleCopy = async (btn) => {
    const sel = btn.getAttribute('data-copy');
    const node = sel ? document.querySelector(sel) : null;
    if (!node) return;

    const text = (node.innerText || node.textContent || '').trim();
    if (!text) return;

    const ok = await copyText(text);
    if (!ok) return;

    const prev = btn.textContent;
    btn.textContent = 'Copiado';
    btn.disabled = true;
    setTimeout(() => {
      btn.disabled = false;
      btn.textContent = prev;
    }, 700);
  };

  // ===== Export CSV
  const btnExport = $('#btnExportCsv');
  if (btnExport) {
    btnExport.addEventListener('click', () => {
      const head = ['RFC','RazonSocial','Email','Phone','Plan','BillingCycle','NextInvoice','CustomAmountMxn','EmailVerif','PhoneVerif','Blocked','CreatedAt'];
      const lines = [];
      lines.push(head.join(','));

      $$('.ac-row[data-export]').forEach((row) => {
        let obj = {};
        try {
          let raw = row.getAttribute('data-export') || '{}';
          raw = decodeHtml(raw);
          obj = JSON.parse(raw);
        } catch (e) {}
        const rowVals = head.map((k) => (obj[k] ?? '').toString());
        lines.push(rowVals.map((t) => (/[",\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t)).join(','));
      });

      const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'clientes_export.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    });
  }

  // ===== Click handler (delegación robusta)
  document.addEventListener('click', (e) => {
    const t = e.target;

    // 1) Abrir drawer
    const openBtn = t.closest('[data-open-drawer]');
    if (openBtn) {
      const row = openBtn.closest('.ac-row');
      const client = parseClient(row);
      if (client) openDrawer(client);
      return;
    }

    // 2) Cerrar drawer (botón o backdrop)
    if (t.closest('[data-close-drawer]')) {
      closeDrawer();
      return;
    }

    // 3) Tabs
    const tabBtn = t.closest('.ac-tab[data-tab]');
    if (tabBtn) {
      handleTabs(tabBtn);
      return;
    }

    // 4) Copiar
    const copyBtn = t.closest('[data-copy]');
    if (copyBtn) {
      e.preventDefault();
      handleCopy(copyBtn);
      return;
    }

    // 5) Abrir modales desde drawer
    const c = drawer && drawer.classList.contains('open') ? (drawer._client || null) : null;

    if (t.closest('#btnOpenEdit')) {
      if (!c) return;
      fillEditModal(c);
      openModal('#modalEdit');
      return;
    }
    if (t.closest('#btnOpenRecipients')) {
      if (!c) return;
      fillRecipientsModal(c);
      openModal('#modalRecipients');
      return;
    }
    if (t.closest('#btnOpenCreds')) {
      if (!c) return;
      fillCredsModal(c);
      openModal('#modalCreds');
      return;
    }
    if (t.closest('#btnOpenBilling')) {
      if (!c) return;
      fillBillingModal(c);
      openModal('#modalBilling');
      return;
    }

    // 6) Cerrar modales (botón o backdrop)
    if (t.closest('[data-close-modal]')) {
      const m = t.closest('.ac-modal');
      closeModal(m);
      return;
    }
  });

  // ===== ESC
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const openModalEl = $('.ac-modal.open');
    if (openModalEl) {
      closeModal(openModalEl);
      return;
    }

    if (drawer && drawer.classList.contains('open')) {
      closeDrawer();
    }
  });

  // ===== Safety: normalizar lock
  (function normalizeInitialState(){
    const anyModalOpen = !!$('.ac-modal.open');
    const drawerOpen = !!(drawer && drawer.classList.contains('open'));
    if (anyModalOpen || drawerOpen) {
      Lock.reset();
      if (drawerOpen) Lock.on();
      if (anyModalOpen) Lock.on();
      document.documentElement.classList.add('ac-lock');
    }
  })();

})();
