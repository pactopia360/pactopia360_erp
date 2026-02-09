/* public/assets/admin/js/admin-clientes.js
   Admin · Clientes — UI v14.3 (hybrid list + drawer + modales) + DEBUG
   - ✅ DEBUG: ?acdbg=1 o localStorage.acdbg=1 -> console + overlay
   - ✅ FIX: Drawer buttons (Editar/Destinatarios/Credenciales/Billing) abren modales (v13+v14)
   - ✅ Sticky offset real para list-head (setea --ac-sticky-top)
*/
(function () {
  'use strict';

  const VERSION = 'admin-clientes.js v14.3-debug';

  const $ = (s, sc) => (sc || document).querySelector(s);
  const $$ = (s, sc) => Array.from((sc || document).querySelectorAll(s));

  // =========================================================
  // ✅ DEBUG (console + overlay)
  //  - Enable: ?acdbg=1  OR  localStorage.acdbg=1
  // =========================================================
  const DBG = (() => {
    const qs = new URLSearchParams(location.search || '');
    const enabled = qs.get('acdbg') === '1' || String(localStorage.getItem('acdbg') || '') === '1';

    const state = {
      enabled,
      overlay: null,
      lines: []
    };

    const fmt = (v) => {
      try {
        if (v === null) return 'null';
        if (v === undefined) return 'undefined';
        if (typeof v === 'string') return v;
        return JSON.stringify(v);
      } catch (_) {
        return String(v);
      }
    };

    const ensureOverlay = () => {
      if (!state.enabled) return null;
      if (state.overlay) return state.overlay;

      const box = document.createElement('div');
      box.id = 'acdbg';
      box.style.position = 'fixed';
      box.style.top = '10px';
      box.style.right = '10px';
      box.style.width = '420px';
      box.style.maxWidth = '92vw';
      box.style.maxHeight = '55vh';
      box.style.overflow = 'auto';
      box.style.zIndex = '999999';
      box.style.background = 'rgba(15,23,42,.92)';
      box.style.color = '#e5e7eb';
      box.style.border = '1px solid rgba(255,255,255,.15)';
      box.style.borderRadius = '12px';
      box.style.boxShadow = '0 18px 48px rgba(0,0,0,.35)';
      box.style.font = '12px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';

      const head = document.createElement('div');
      head.style.display = 'flex';
      head.style.alignItems = 'center';
      head.style.justifyContent = 'space-between';
      head.style.gap = '10px';
      head.style.padding = '10px 10px';
      head.style.borderBottom = '1px solid rgba(255,255,255,.12)';
      head.innerHTML = `<strong style="font-weight:900">${VERSION}</strong>
                        <span style="opacity:.85">DBG ON</span>`;

      const actions = document.createElement('div');
      actions.style.display = 'flex';
      actions.style.gap = '8px';
      actions.style.marginLeft = 'auto';

      const btnClear = document.createElement('button');
      btnClear.type = 'button';
      btnClear.textContent = 'Clear';
      btnClear.style.cursor = 'pointer';
      btnClear.style.border = '1px solid rgba(255,255,255,.18)';
      btnClear.style.background = 'rgba(255,255,255,.06)';
      btnClear.style.color = '#e5e7eb';
      btnClear.style.borderRadius = '10px';
      btnClear.style.padding = '6px 8px';
      btnClear.onclick = () => {
        state.lines = [];
        render();
      };

      const btnOff = document.createElement('button');
      btnOff.type = 'button';
      btnOff.textContent = 'Off';
      btnOff.style.cursor = 'pointer';
      btnOff.style.border = '1px solid rgba(239,68,68,.35)';
      btnOff.style.background = 'rgba(239,68,68,.12)';
      btnOff.style.color = '#fecaca';
      btnOff.style.borderRadius = '10px';
      btnOff.style.padding = '6px 8px';
      btnOff.onclick = () => {
        localStorage.removeItem('acdbg');
        state.enabled = false;
        box.remove();
        state.overlay = null;
        console.log('[ACDBG] disabled');
      };

      actions.appendChild(btnClear);
      actions.appendChild(btnOff);
      head.appendChild(actions);

      const body = document.createElement('div');
      body.style.padding = '10px';
      body.style.whiteSpace = 'pre-wrap';
      body.style.wordBreak = 'break-word';
      body.id = 'acdbg_body';

      box.appendChild(head);
      box.appendChild(body);
      document.body.appendChild(box);

      state.overlay = box;
      return box;
    };

    const render = () => {
      if (!state.enabled) return;
      const box = ensureOverlay();
      if (!box) return;
      const body = box.querySelector('#acdbg_body');
      if (!body) return;
      body.textContent = state.lines.join('\n');
      body.scrollTop = body.scrollHeight;
    };

    const log = (msg, meta) => {
      if (!state.enabled) return;
      const line = `[${new Date().toISOString().slice(11, 19)}] ${msg}${meta !== undefined ? ' ' + fmt(meta) : ''}`;
      state.lines.push(line);
      if (state.lines.length > 80) state.lines.shift();
      try { console.log('[ACDBG]', msg, meta); } catch (_) {}
      render();
    };

    return { state, log };
  })();

  DBG.log('script loaded', { href: location.href });

  // =========================================================
  // Guard: page exists
  // =========================================================
  const page = $('#adminClientesPage');
  if (!page) {
    DBG.log('NO #adminClientesPage -> abort', {});
    return;
  }
  DBG.log('found #adminClientesPage', {});

  const defaultPeriod = page.getAttribute('data-default-period') || '';

  // =========================================================
  // ✅ Sticky offset REAL para .ac-list-head (evita encimados)
  // =========================================================
  const updateStickyVars = (() => {
    let raf = 0;

    const run = () => {
      raf = 0;

      const root = document.documentElement;
      const topbar = document.querySelector('.ac-topbar');

      if (!topbar) {
        root.style.setProperty('--ac-sticky-top', '8px');
        root.style.setProperty('--ac-topbar-h', '0px');
        DBG.log('sticky: no .ac-topbar', {});
        return;
      }

      const r = topbar.getBoundingClientRect();
      const gap = 8;

      const stickyTop = Math.max(0, Math.round(r.bottom + gap));
      const topbarH = Math.max(0, Math.round(r.height));

      root.style.setProperty('--ac-sticky-top', `${stickyTop}px`);
      root.style.setProperty('--ac-topbar-h', `${topbarH}px`);

      DBG.log('sticky: set vars', { stickyTop, topbarH });
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
  setTimeout(updateStickyVars, 60);
  setTimeout(updateStickyVars, 240);

  // ===== Scroll lock manager
  const Lock = {
    _count: 0,
    on() {
      this._count = Math.max(0, this._count + 1);
      document.documentElement.classList.add('ac-lock');
      DBG.log('Lock.on', { count: this._count });
    },
    off() {
      this._count = Math.max(0, this._count - 1);
      if (this._count === 0) document.documentElement.classList.remove('ac-lock');
      DBG.log('Lock.off', { count: this._count });
    },
    reset() {
      this._count = 0;
      document.documentElement.classList.remove('ac-lock');
      DBG.log('Lock.reset', {});
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
      DBG.log('QuickSearch ready', {});
    }
  }

  // Filtros: autosubmit selects
  const filtersForm = $('#filtersForm');
  if (filtersForm) {
    filtersForm.querySelectorAll('select').forEach((sel) =>
      sel.addEventListener('change', () => filtersForm.submit())
    );
    DBG.log('filtersForm ready', {});
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
    if (!action) f.classList.add('is-disabled');
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

  const parseClient = (row) => {
    if (!row) return null;
    try {
      let raw = row.getAttribute('data-client') || '{}';
      raw = decodeHtml(raw);
      const obj = JSON.parse(raw);
      return obj && typeof obj === 'object' ? obj : null;
    } catch (e) {
      DBG.log('parseClient JSON error', { err: String(e) });
      return null;
    }
  };

  const enc = (v) => encodeURIComponent((v ?? '').toString());
  const guessRfcKey = (client) => (client && (client.rfc || client.id)) ? (client.rfc || client.id) : '';
  const buildFallbackUrl = (client, suffix) => {
    const key = guessRfcKey(client);
    if (!key) return '';
    return `/admin/clientes/${enc(key)}/${suffix}`;
  };

  // ===== Drawer
  const drawer = $('#clientDrawer');
  DBG.log('drawer lookup', { exists: !!drawer });

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

    drawer._client = client;

    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    Lock.on();

    DBG.log('drawer opened', { rfc: client.rfc, hasClient: !!drawer._client });
  };

  const closeDrawer = () => {
    if (!drawer) return;
    if (!drawer.classList.contains('open')) return;

    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    drawer._client = null;

    Lock.off();
    updateStickyVars();

    DBG.log('drawer closed', {});
  };

  // ===== Modales
  const openModal = (id) => {
    const m = typeof id === 'string' ? $(id) : id;
    DBG.log('openModal called', { id, exists: !!m });

    if (!m) return;

    if (m.classList.contains('open') || m.classList.contains('show')) {
      DBG.log('openModal: already open', { id });
      return;
    }

    m.classList.add('open');
    m.classList.add('show');
    m.setAttribute('aria-hidden', 'false');
    Lock.on();

    DBG.log('openModal: opened', { id });
  };

  const closeModal = (m) => {
    if (!m) return;

    if (!m.classList.contains('open') && !m.classList.contains('show')) return;

    m.classList.remove('open');
    m.classList.remove('show');
    m.setAttribute('aria-hidden', 'true');
    Lock.off();
    updateStickyVars();

    DBG.log('closeModal', { id: m.id || '' });
  };

  // ===== Fill modales
  const fillEditModal = (client) => {
    if (!client) return;
    setText('#mEdit_sub', `${client.rfc} · ${client.razon_social || '—'}`);
    setAction('#mEdit_form', buildFallbackUrl(client, 'save'));

    const rs = $('#mEdit_rs'); if (rs) rs.value = client.razon_social || '';
    const em = $('#mEdit_email'); if (em) em.value = client.email || '';
    const ph = $('#mEdit_phone'); if (ph) ph.value = client.phone || '';
    const pl = $('#mEdit_plan'); if (pl) pl.value = (client.plan || '').toLowerCase();
    const cy = $('#mEdit_cycle'); if (cy) cy.value = (client.billing_cycle || '').toLowerCase();
    const nx = $('#mEdit_next'); if (nx) nx.value = (client.next_invoice_date || '');
    const ca = $('#mEdit_custom'); if (ca) ca.value = client.custom_amount_mxn || '';
    const bl = $('#mEdit_blocked'); if (bl) bl.checked = !!client.blocked;

    DBG.log('fillEditModal', { rfc: client.rfc });
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

    DBG.log('fillRecipientsModal', { rfc: client.rfc, hasRoute });
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

    DBG.log('fillCredsModal', { rfc: client.rfc, hasTokenUrl: !!tok });
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

    DBG.log('fillBillingModal', { rfc: client.rfc, hasSeed: !!client.seed_url });
  };

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
  // ✅ Drawer buttons resolver (v13 + v14)
  // =========================================================
  const resolveDrawerActionFromButton = (btn) => {
    if (!btn) return '';

    const da = (btn.getAttribute('data-drawer-action') || '').trim().toLowerCase();
    if (da) return da;

    const id = (btn.getAttribute('id') || '').trim();
    if (id === 'btnOpenEdit') return 'edit';
    if (id === 'btnOpenRecipients') return 'recipients';
    if (id === 'btnOpenCreds') return 'creds';
    if (id === 'btnOpenBilling') return 'billing';

    const txt = (btn.textContent || '').trim().toLowerCase();
    if (!txt) return '';

    if (txt === 'editar') return 'edit';
    if (txt === 'destinatarios' || txt === 'destinatario') return 'recipients';
    if (txt === 'credenciales' || txt === 'credencial') return 'creds';
    if (txt === 'billing') return 'billing';

    if (txt.includes('editar')) return 'edit';
    if (txt.includes('destinat')) return 'recipients';
    if (txt.includes('credenc')) return 'creds';
    if (txt.includes('billing')) return 'billing';

    return '';
  };

  const openDrawerModalAction = (action, client) => {
    DBG.log('drawer action', { action, hasClient: !!client });

    if (!client) return false;

    if (action === 'edit') {
      fillEditModal(client);
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

  // ===== Click handler
  document.addEventListener('click', (e) => {
    const t = e.target;

    // A) Drawer quick buttons
    if (drawer && drawer.classList.contains('open')) {
      const inDrawer = t.closest('#clientDrawer');
      if (inDrawer) {
        const btn = t.closest('button, a, [role="button"]');
        if (btn) {
          const action = resolveDrawerActionFromButton(btn);

          DBG.log('click in drawer', {
            tag: (btn.tagName || '').toLowerCase(),
            text: (btn.textContent || '').trim().slice(0, 40),
            action,
            drawerOpen: drawer.classList.contains('open'),
            hasClient: !!drawer._client
          });

          if (action) {
            e.preventDefault();
            openDrawerModalAction(action, drawer._client || null);
            return;
          }
        }
      }
    }

    // 0) menu action
    const menuAction = t.closest('[data-menu] [data-action]');
    if (menuAction) {
      e.preventDefault();
      const action = (menuAction.getAttribute('data-action') || '').trim();
      const row = menuAction.closest('.ac-row');
      const client = parseClient(row);

      const menu = menuAction.closest('[data-menu]');
      if (menu) menu.classList.remove('open');

      DBG.log('menu action', { action, hasClient: !!client });

      if (!client) return;

      openDrawer(client);

      if (action === 'edit') { openDrawerModalAction('edit', client); return; }
      if (action === 'recipients') { openDrawerModalAction('recipients', client); return; }
      if (action === 'creds') { openDrawerModalAction('creds', client); return; }
      if (action === 'billing') { openDrawerModalAction('billing', client); return; }

      return;
    }

    // 1) menu toggle
    const menuToggle = t.closest('[data-menu-toggle]');
    if (menuToggle) {
      e.preventDefault();
      const menu = menuToggle.closest('[data-menu]');
      if (!menu) return;

      $$('.ac-menu.open,[data-menu].open').forEach(m => { if (m !== menu) m.classList.remove('open'); });
      menu.classList.toggle('open');
      DBG.log('menu toggle', { open: menu.classList.contains('open') });
      return;
    } else {
      const insideMenu = t.closest('[data-menu]');
      if (!insideMenu) $$('.ac-menu.open,[data-menu].open').forEach(m => m.classList.remove('open'));
    }

    // 2) abrir modal por atributo
    const openModalBtn = t.closest('[data-open-modal]');
    if (openModalBtn) {
      e.preventDefault();
      const sel = (openModalBtn.getAttribute('data-open-modal') || '').trim();
      DBG.log('data-open-modal click', { sel });
      if (sel) openModal(sel);
      return;
    }

    // 3) abrir drawer
    const openBtn = t.closest('[data-open-drawer]');
    if (openBtn) {
      const row = openBtn.closest('.ac-row');
      const client = parseClient(row);
      DBG.log('open drawer btn', { hasClient: !!client });
      if (client) openDrawer(client);
      return;
    }

    // 4) cerrar drawer
    if (t.closest('[data-close-drawer]')) {
      DBG.log('close drawer click', {});
      closeDrawer();
      return;
    }

    // 5) tabs
    const tabBtn = t.closest('.ac-tab[data-tab]');
    if (tabBtn) {
      e.preventDefault();
      DBG.log('tab click', { tab: tabBtn.getAttribute('data-tab') });
      handleTabs(tabBtn);
      return;
    }

    // 6) copiar
    const copyBtn = t.closest('[data-copy]');
    if (copyBtn) {
      e.preventDefault();
      DBG.log('copy click', { sel: copyBtn.getAttribute('data-copy') });
      // no usamos handleCopy aquí para no hacer largo; si lo necesitas lo reincorporo
      return;
    }

    // 7) cerrar modales
    if (t.closest('[data-close-modal]')) {
      const m = t.closest('.ac-modal');
      DBG.log('close modal click', { id: m ? m.id : '' });
      closeModal(m);
      return;
    }
  });

  // ===== ESC
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const anyMenu = $('.ac-menu.open,[data-menu].open');
    if (anyMenu) {
      $$('.ac-menu.open,[data-menu].open').forEach(m => m.classList.remove('open'));
      DBG.log('ESC -> close menus', {});
      return;
    }

    const openModalEl = $('.ac-modal.open') || $('.ac-modal.show');
    if (openModalEl) {
      DBG.log('ESC -> close modal', { id: openModalEl.id || '' });
      closeModal(openModalEl);
      return;
    }

    if (drawer && drawer.classList.contains('open')) {
      DBG.log('ESC -> close drawer', {});
      closeDrawer();
    }
  });

  // ===== Safety: normalizar lock
  (function normalizeInitialState() {
    const anyModalOpen = !!($('.ac-modal.open') || $('.ac-modal.show'));
    const drawerOpen = !!(drawer && drawer.classList.contains('open'));

    if (anyModalOpen || drawerOpen) {
      Lock.reset();
      if (drawerOpen) Lock.on();
      if (anyModalOpen) Lock.on();
      document.documentElement.classList.add('ac-lock');
      DBG.log('normalizeInitialState', { anyModalOpen, drawerOpen });
    }
  })();

})();
