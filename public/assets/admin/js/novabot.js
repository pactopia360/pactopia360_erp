/* ============================================================================
   NovaBot · Asistente ligero para el panel (SPA-lite friendly)
   - UI autoinyectable (si no existen #novaPanel / #novaToggle)
   - API pública: window.NovaBot.open(), .close(), .toggle(), .say(), .push()
   - Historial en localStorage (máx 50 mensajes)
   - Sugerencias rápidas, “typing”, slash-commands
   - Integrado con P360 (beacon logs, toast opcional, eventos)
   ============================================================================ */
(function () {
  'use strict';

  // ---------- Helpers mínimos ----------
  const W = window, D = document, B = D.body, root = D.documentElement;
  const $  = (s, c) => (c || D).querySelector(s);
  const $$ = (s, c) => Array.from((c || D).querySelectorAll(s));
  const on = (el, ev, fn, opt) => el && el.addEventListener(ev, fn, opt || false);

  function safeHTML(s) {
    const d = D.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }

  function toast(msg, type) {
    try {
      if (W.P360 && P360.toast) {
        (type === 'error' ? P360.toast.error : P360.toast.info)(msg);
      } else {
        console.log('[NovaBot]', msg);
      }
    } catch (_) {}
  }

  // ==== URL helpers (admin y log) ====
  function adminUrl(path) {
    if (W.P360 && P360.url && typeof P360.url.admin === 'function') return P360.url.admin(path || '');
    const base = (D.querySelector('meta[name="p360-admin"]')?.content || (location.origin + '/admin')).replace(/\/+$/, '');
    path = String(path || '').replace(/^\/+/, '');
    return base + (path ? '/' + path : '');
  }
  function logUrl() {
    return D.querySelector('meta[name="p360-log"]')?.content || adminUrl('ui/log');
  }

  // ==== P360.debug (sendBeacon + fallback GET SIN CSRF) ====
  (function ensureDebug(){
    const Debug = {
      /**
       * Envía beacon a /admin/ui/log sin requerir CSRF:
       * - 1) sendBeacon con JSON
       * - 2) fallback GET con querystring (no CORS/CSRF)
       */
      send(channel, data) {
        const url = logUrl();
        const payload = {
          channel: channel || 'ui',
          at: Date.now(),
          ua: navigator.userAgent,
          location: (location.href || ''),
          data: data || {}
        };

        try {
          if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
            return true;
          }
        } catch (_) {}

        // Fallback GET
        try {
          const q = new URLSearchParams();
          q.set('ch', payload.channel);
          q.set('at', String(payload.at));
          q.set('ua', payload.ua);
          q.set('loc', payload.location);
          q.set('p', JSON.stringify(payload.data));
          fetch(url + '?' + q.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          }).catch(()=>{});
        } catch (_) {}

        return false;
      }
    };

    W.P360 = W.P360 || {};
    if (!W.P360.debug || typeof W.P360.debug.send !== 'function') {
      W.P360.debug = Debug;
    }
  })();

  function beacon(channel, data) {
    try { if (W.P360 && P360.debug && typeof P360.debug.send === 'function') P360.debug.send(channel || 'novabot', data); } catch (_) {}
  }

  // ---------- Storage del historial ----------
  const STORAGE_KEY = 'novabot.history';
  const MAX_ITEMS = 50;

  function readHistory() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list.slice(-MAX_ITEMS) : [];
    } catch (_) {
      return [];
    }
  }
  function writeHistory(arr) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(arr.slice(-MAX_ITEMS))); } catch (_) {}
  }
  function pushHistory(role, content) {
    const item = { role, content: String(content || ''), at: new Date().toISOString() };
    const arr = readHistory();
    arr.push(item);
    writeHistory(arr);
    return item;
  }
  function clearHistory() { try { localStorage.removeItem(STORAGE_KEY); } catch (_) {} }

  // ---------- UI autoinyectable ----------
  function ensureStyles() {
    if (D.getElementById('novaStyles')) return;
    const s = D.createElement('style'); s.id = 'novaStyles';
    s.textContent = `
      .nova-fab{position:fixed;right:18px;bottom:18px;width:52px;height:52px;border-radius:16px;border:1px solid rgba(0,0,0,.12);
        background:linear-gradient(180deg,rgba(99,102,241,.12),transparent);backdrop-filter:saturate(160%) blur(6px);
        display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10020;box-shadow:0 8px 24px rgba(0,0,0,.15)}
      html.theme-dark .nova-fab{border-color:rgba(255,255,255,.12)}
      .nova-panel{position:fixed;right:20px;bottom:84px;width:360px;max-width:calc(100vw - 24px);height:520px;max-height:calc(100vh - 120px);
        background:var(--nv-bg,#fff);color:inherit;border-radius:16px;border:1px solid rgba(0,0,0,.12);display:none;flex-direction:column;overflow:hidden;z-index:10025;box-shadow:0 12px 36px rgba(0,0,0,.22)}
      html.theme-dark .nova-panel{--nv-bg:#0f1620;border-color:rgba(255,255,255,.12)}
      .nova-panel.open{display:flex}
      .nova-head{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.08)}
      html.theme-dark .nova-head{border-bottom-color:rgba(255,255,255,.1)}
      .nova-title{display:flex;align-items:center;gap:8px;font-weight:700}
      .nova-dot{width:8px;height:8px;border-radius:9999px;background:#10b981;box-shadow:0 0 0 6px rgba(16,185,129,.18)}
      .nova-close{appearance:none;background:none;border:0;font-size:18px;line-height:1;cursor:pointer;opacity:.8}
      .nova-log{flex:1;overflow:auto;padding:12px 12px 0;display:flex;flex-direction:column;gap:8px}
      .nova-msg{max-width:82%;padding:8px 10px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.12)}
      .nova-msg.user{align-self:flex-end;background:#eef2ff}
      .nova-msg.bot{align-self:flex-start;background:#f3f4f6}
      html.theme-dark .nova-msg.user{background:rgba(99,102,241,.18)}
      html.theme-dark .nova-msg.bot{background:rgba(255,255,255,.06)}
      .nova-typing{align-self:flex-start;display:flex;gap:4px;padding:8px 10px;border-radius:12px;background:rgba(0,0,0,.06)}
      html.theme-dark .nova-typing{background:rgba(255,255,255,.08)}
      .nova-typing .d{width:6px;height:6px;border-radius:9999px;background:currentColor;opacity:.5;animation:ndots 1s infinite}
      .nova-typing .d:nth-child(2){animation-delay:.15s}.nova-typing .d:nth-child(3){animation-delay:.3s}
      @keyframes ndots{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
      .nova-sug{display:flex;flex-wrap:wrap;gap:8px;padding:10px;border-top:1px dashed rgba(0,0,0,.08)}
      html.theme-dark .nova-sug{border-top-color:rgba(255,255,255,.1)}
      .nova-btn{appearance:none;border:1px solid rgba(0,0,0,.1);background:transparent;border-radius:9999px;padding:6px 10px;cursor:pointer;font:12px/1 system-ui}
      html.theme-dark .nova-btn{border-color:rgba(255,255,255,.15);color:#e5e7eb}
      .nova-input{display:flex;align-items:center;gap:8px;padding:10px;border-top:1px solid rgba(0,0,0,.08)}
      html.theme-dark .nova-input{border-top-color:rgba(255,255,255,.1)}
      .nova-input input{flex:1;min-width:0;padding:10px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:transparent;color:inherit}
      html.theme-dark .nova-input input{border-color:rgba(255,255,255,.15)}
      .nova-input button{padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:#111827;color:#fff;cursor:pointer}
      .nova-off{opacity:.6}
    `;
    D.head.appendChild(s);
  }

  function ensureUI() {
    ensureStyles();

    let panel = $('#novaPanel');
    let fab   = $('#novaToggle');
    let close = $('#novaClose');
    let log   = $('#novaLog');
    let input = $('#novaInput');
    let send  = $('#novaSend');
    let sugCt = $('#novaSug');

    // Si faltan, se inyectan
    if (!panel) {
      panel = D.createElement('div');
      panel.id = 'novaPanel';
      panel.className = 'nova-panel';
      panel.setAttribute('aria-live', 'polite');
      panel.innerHTML = `
        <div class="nova-head">
          <div class="nova-title">
            <span class="nova-dot" id="novaDot" title="Conectado"></span>
            NovaBot
          </div>
          <button class="nova-close" id="novaClose" title="Cerrar">✕</button>
        </div>
        <div class="nova-log" id="novaLog" role="log" aria-live="polite"></div>
        <div class="nova-sug" id="novaSug"></div>
        <div class="nova-input">
          <input id="novaInput" type="text" placeholder="Escribe tu pregunta… (Enter para enviar)" autocomplete="off">
          <button id="novaSend" type="button">Enviar</button>
        </div>
      `;
      B.appendChild(panel);
    }
    if (!fab) {
      fab = D.createElement('button');
      fab.id = 'novaToggle';
      fab.className = 'nova-fab';
      fab.innerHTML = '🤖';
      fab.title = 'Asistente';
      B.appendChild(fab);
    }

    // Reasignar refs
    close = $('#novaClose'); log = $('#novaLog'); input = $('#novaInput'); send = $('#novaSend'); sugCt = $('#novaSug');

    return { panel, fab, close, log, input, send, sugCt };
  }

  // ---------- Bot core ----------
  const UI = ensureUI();
  let typingEl = null;
  let backendUrl = null; // opcional (p.ej. admin/bot/ask)
  const defaultSuggestions = [
    '¿Qué hay nuevo hoy?',
    'Ver últimos pagos',
    'Crear cliente rápido',
    'Estadísticas del mes',
    'Ayuda / comandos'
  ];

  function open()  { UI.panel.classList.add('open'); beacon('novabot', { event: 'open' }); }
  function close() { UI.panel.classList.remove('open'); beacon('novabot', { event: 'close' }); }
  function toggle(){ UI.panel.classList.toggle('open'); beacon('novabot', { event: 'toggle', open: UI.panel.classList.contains('open') }); }

  function push(role, text) {
    if (!UI.log) return;
    const div = D.createElement('div');
    div.className = 'nova-msg ' + (role === 'user' ? 'user' : 'bot');
    div.innerHTML = safeHTML(text);
    UI.log.appendChild(div);
    UI.log.scrollTo({ top: UI.log.scrollHeight, behavior: 'smooth' });
    pushHistory(role, text);
    return div;
  }

  function typing(on) {
    if (!UI.log) return;
    if (on) {
      if (typingEl) return;
      typingEl = D.createElement('div');
      typingEl.className = 'nova-typing';
      typingEl.innerHTML = '<span class="d"></span><span class="d"></span><span class="d"></span>';
      UI.log.appendChild(typingEl);
      UI.log.scrollTo({ top: UI.log.scrollHeight, behavior: 'smooth' });
    } else {
      try { typingEl?.remove(); } catch (_) {}
      typingEl = null;
    }
  }

  function setSuggestions(list) {
    if (!UI.sugCt) return;
    UI.sugCt.innerHTML = '';
    (list && list.length ? list : defaultSuggestions).forEach(t => {
      const b = D.createElement('button');
      b.className = 'nova-btn'; b.type = 'button'; b.textContent = t;
      on(b, 'click', () => { UI.input.value = t; UI.input.focus(); });
      UI.sugCt.appendChild(b);
    });
  }

  async function fakeAnswer(q) { return 'Anotado: ' + q + '\n\n(/help para ver comandos. Integra backend con NovaBot.setBackend(url).)'; }

  async function askBackend(q, history) {
    if (!backendUrl) return fakeAnswer(q);
    try {
      const res = await fetch(backendUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify({ q, history })
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const ct = res.headers.get('content-type') || '';
      if (ct.includes('application/json')) {
        const data = await res.json();
        return (data && (data.answer || data.text || data.html)) || '(Sin respuesta)';
      }
      return await res.text();
    } catch (err) {
      console.error('[NovaBot] backend error:', err);
      return 'No pude contactar al backend (' + (err.message || 'error') + '). Usando respuesta simulada.';
    }
  }

  // ---------- Slash commands ----------
  async function handleCommand(cmd) {
    const name = cmd.replace(/^\s*\/+/, '').split(/\s+/)[0].toLowerCase();
    const arg  = cmd.replace(/^\s*\/+\w+\s*/, '');
    switch (name) {
      case 'help':
        return [
          'Comandos disponibles:',
          '• /help — muestra esta ayuda',
          '• /clear — limpia historial',
          '• /ping — prueba de latido del servidor',
          '• /theme — alterna tema claro/oscuro',
          '• /log on|off — logs verbosos',
        ].join('\n');
      case 'clear':
        clearHistory(); if (UI.log) UI.log.innerHTML = ''; return 'Historial limpiado.';
      case 'ping': {
        const url = $('#topbar')?.dataset.heartbeatUrl || adminUrl('ui/heartbeat');
        try {
          const t0 = performance.now();
          const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
          const dt = Math.round(performance.now() - t0);
          return res.ok ? `PONG ✔ (${dt} ms)` : `PONG ⚠ HTTP ${res.status} (${dt} ms)`;
        } catch { return 'PONG ✖ sin respuesta'; }
      }
      case 'theme': {
        try {
          const key = 'p360-theme';
          const cur = (localStorage.getItem(key) === 'dark') ? 'dark' : 'light';
          const next = (cur === 'dark') ? 'light' : 'dark';
          localStorage.setItem(key, next);
          root.classList.toggle('theme-dark', next === 'dark');
          root.classList.toggle('theme-light', next !== 'dark');
          root.setAttribute('data-theme', next);
          return `Tema cambiado a ${next.toUpperCase()}.`;
        } catch { return 'No se pudo cambiar el tema.'; }
      }
      case 'log': {
        const onoff = arg.trim().toLowerCase();
        const key = 'p360.log.verbose';
        const on = (onoff === 'on' || onoff === '1' || onoff === 'true');
        try { localStorage.setItem(key, on ? '1' : '0'); } catch {}
        if (W.P360) {
          P360.log = (...a) => { if (on) { try { console.debug('%c[P360]', 'color:#6366f1;font-weight:700', ...a); } catch (_) {} } };
        }
        return 'Logs verbosos: ' + (on ? 'ON' : 'OFF');
      }
      default:
        return `No reconozco el comando “/${name}”. Usa /help.`;
    }
  }

  async function sendMsg() {
    const q = (UI.input?.value || '').trim();
    if (!q) return;
    push('user', q);
    UI.input.value = '';

    if (q.startsWith('/')) {
      typing(true);
      const ans = await handleCommand(q);
      typing(false);
      push('bot', ans);
      return;
    }

    typing(true);
    const answer = await askBackend(q, readHistory());
    typing(false);
    push('bot', answer);
    beacon('novabot', { event: 'message', q });
  }

  // ---------- Eventos UI ----------
  function bindUI() {
    $$('.nova-btn').forEach(b => {
      if (b.dataset.bound) return;
      b.dataset.bound = '1';
      on(b, 'click', () => { if (UI.input) { UI.input.value = b.dataset.q || b.textContent || ''; UI.input.focus(); } });
    });

    on(UI.fab,   'click', open);
    on(UI.close, 'click', close);

    on(UI.send, 'click', sendMsg);
    on(UI.input, 'keydown', (e) => { if (e.key === 'Enter') sendMsg(); });

    on(W, 'keydown', (e) => {
      if (e.ctrlKey && e.shiftKey && (e.key || '').toLowerCase() === 'b') {
        e.preventDefault(); toggle();
      }
    });

    on(W, 'p360:bot:open', open);

    on(W, 'online', () => { $('#novaDot')?.classList.remove('nova-off'); $('#novaDot')?.setAttribute('title','Conectado'); });
    on(W, 'offline', () => { $('#novaDot')?.classList.add('nova-off'); $('#novaDot')?.setAttribute('title','Sin conexión'); });

    setSuggestions();
  }

  bindUI();

  // ---------- API pública ----------
  const API = {
    open, close, toggle,
    say(text) { open(); push('bot', text); },
    push,
    clear() { clearHistory(); if (UI.log) UI.log.innerHTML = ''; },
    setSuggestions(list) { setSuggestions(list); },
    setBackend(url) { backendUrl = url; },
    history() { return readHistory(); },
    openWith(text) { open(); if (UI.input) { UI.input.value = text || ''; UI.input.focus(); } },
  };
  W.NovaBot = API;

  (function initBackendFromMeta() {
    const m = D.querySelector('meta[name="p360-bot"]');
    if (m && m.content) backendUrl = m.content;
  })();

  (function greet() {
    if (readHistory().length) return;
    push('bot', '¡Hola! Soy NovaBot. Puedo ayudarte dentro del panel.\n\nComienza con alguna sugerencia o escribe /help para ver comandos.');
  })();

  beacon('novabot', { event: 'ready', ts: Date.now(), ua: navigator.userAgent });
})();
