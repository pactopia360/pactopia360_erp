<!-- Config global vía <meta> (redundante si ya están en <head>, es inofensivo) -->
<meta name="p360-csrf"  content="{{ csrf_token() }}">
<meta name="p360-base"  content="{{ url('/') }}">
<meta name="p360-admin" content="{{ url('/admin') }}">
<meta name="p360-env"   content="{{ app()->environment() }}">

<script>
/* =============================================================================
   P360 · Bootstrap + Utils + Logger + Beacon
   ============================================================================= */
(function () {
  'use strict';

  // --- Lee metatags ---
  function m(name){ var el=document.querySelector('meta[name="'+name+'"]'); return el ? el.content : ''; }

  // --- Espacio global ---
  window.P360 = window.P360 || {};
  P360.csrf      = m('p360-csrf');
  P360.baseUrl   = (m('p360-base')  || location.origin).replace(/\/+$/,'');
  P360.adminBase = (m('p360-admin') || (location.origin + '/admin')).replace(/\/+$/,'');
  P360.env       = m('p360-env') || 'production';

  // --- Logger mínimo ---
  P360.log = P360.log || ((...a)=>{ try{ console.debug('%c[P360]', 'color:#6366f1;font-weight:700', ...a); }catch(_){} });

  // --- Toasts mínimos ---
  P360.toast = P360.toast || (function(){
    let host = null;
    function ensureHost(){
      if (host && document.body.contains(host)) return host;
      host = document.createElement('div');
      host.id = 'p360-toasts';
      host.style.cssText = 'position:fixed;right:12px;top:12px;z-index:10000;display:flex;flex-direction:column;gap:8px;';
      document.body.appendChild(host);
      return host;
    }
    function show(msg, type){
      const h = ensureHost();
      const el = document.createElement('div');
      el.className = 'toast toast-'+(type||'info');
      el.style.cssText = 'background:#111827;color:#fff;padding:10px 12px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.2);max-width:360px';
      if (type==='error') el.style.background = '#b91c1c';
      el.innerHTML = '<span style="vertical-align:middle">'+String(msg||'')+'</span>';
      h.appendChild(el);
      setTimeout(()=>{ try{ el.remove(); }catch(_){ } }, 4200);
    }
    return { info:(m)=>show(m,'info'), error:(m)=>show(m,'error'), success:(m)=>show(m,'info') };
  })();

  // --- Helpers DOM / tiempo ---
  const util = {
    qs:  (s,sc)=> (sc||document).querySelector(s),
    qsa: (s,sc)=> Array.prototype.slice.call((sc||document).querySelectorAll(s)),
    once: (el,ev,fn)=>{ const h=(e)=>{ el.removeEventListener(ev,h); fn(e); }; el.addEventListener(ev,h); },
    debounce(fn,wait){ let t; return function(...a){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,a), wait); }; },
    throttle(fn,wait){ let p=0; return function(...a){ const n=Date.now(); if(n-p>=wait){ p=n; fn.apply(this,a);} }; },
    eid(){ return Math.random().toString(36).slice(2); },
    nextFrame(){ return new Promise(r=>requestAnimationFrame(()=>r())); },
  };
  P360.util = P360.util || util;

  // --- URL helpers ---
  P360.url = {
    join(base, path){ base=String(base||'').replace(/\/+$/,''); path=String(path||'').replace(/^\/+/,''); return base + (path?('/'+path):''); },
    base(path){ return this.join(P360.baseUrl, path); },
    admin(path){ return this.join(P360.adminBase, path); },
    isSameOrigin(url){ try{ const u=new URL(url, location.href); return u.origin===location.origin; }catch(_){ return false; } },
    isAdminPath(url){ try{ const u=new URL(url, location.href); const pAdmin=new URL(P360.adminBase, location.href).pathname; return u.pathname.startsWith(pAdmin); }catch(_){ return false; } },
  };

  // --- HTTP wrapper con CSRF ---
  P360.http = P360.http || (function(){
    const METHODS = ['GET','POST','PUT','PATCH','DELETE'];
    const sameOrigin = (url)=>{ try{ const u=new URL(url, location.origin); return u.origin===location.origin; }catch(_){ return true; } };
    async function core(url, opts){
      opts = opts || {};
      opts.method = (opts.method||'GET').toUpperCase();
      opts.headers = new Headers(opts.headers || {});
      if (sameOrigin(url) && !['GET','HEAD','OPTIONS'].includes(opts.method)) {
        if (!opts.headers.has('X-CSRF-TOKEN')) opts.headers.set('X-CSRF-TOKEN', P360.csrf || '');
      }
      if (!opts.headers.has('X-Requested-With')) opts.headers.set('X-Requested-With','XMLHttpRequest');
      if (opts.json && !opts.body) {
        opts.headers.set('Content-Type','application/json');
        opts.body = JSON.stringify(opts.json);
        delete opts.json;
      }
      opts.credentials = opts.credentials || 'same-origin';
      const res = await fetch(url, opts);
      const ct = res.headers.get('content-type') || '';
      const payload = ct.includes('application/json') ? await res.json().catch(()=>null) : await res.text().catch(()=>null);
      if (!res.ok) { const err = new Error('HTTP '+res.status); err.status = res.status; err.data = payload; throw err; }
      return payload;
    }
    const api = { fetch: core };
    METHODS.forEach(m=>{ api[m.toLowerCase()] = (url, opts)=> core(url, Object.assign({}, opts||{}, { method:m })); });
    api.postJson  = (url, data, opts)=> core(url, Object.assign({ method:'POST',  json:data }, opts||{}));
    api.putJson   = (url, data, opts)=> core(url, Object.assign({ method:'PUT',   json:data }, opts||{}));
    api.patchJson = (url, data, opts)=> core(url, Object.assign({ method:'PATCH', json:data }, opts||{}));
    return api;
  })();

  // --- Event bus ---
  P360.events = P360.events || (function(){ const bus=document.createElement('span'); return {
    on:(t,f)=>bus.addEventListener(t,f), off:(t,f)=>bus.removeEventListener(t,f), emit:(t,d)=>bus.dispatchEvent(new CustomEvent(t,{detail:d}))
  }; })();

  // --- Debug Beacon (opcional) ---
  P360.debug = P360.debug || {};
  P360.debug.send = function(channel, data){
    try{
      const url = P360.url.admin('ui/log');
      const payload = { channel, data, at: new Date().toISOString() };

      if (navigator.sendBeacon) {
        const blob = new Blob([JSON.stringify(payload)], { type:'application/json' });
        if (navigator.sendBeacon(url, blob)) return true;
      }
      // Fallback fetch
      fetch(url, {
        method:'POST',
        headers:{ 'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest' },
        body: JSON.stringify(payload),
        credentials:'same-origin'
      }).catch(()=> {
        // Último recurso: GET "pixel"
        new Image().src = url + '?payload=' + encodeURIComponent(JSON.stringify(payload));
      });
    }catch(_){}
    return false;
  };

})();
</script>

<!-- =============================================================================
     UI Orchestrator: Theme · Sidebar (desktop/móvil) · Menús · Búsqueda · Debug
     ============================================================================= -->
<script>
(function(){
  'use strict';

  const root=document.documentElement, body=document.body;
  const { qs, qsa, debounce, nextFrame } = P360.util;
  const log = P360.log;

  /* =========================
     THEME (persistente)
     ========================= */
  (function theme(){
    const KEY='p360-theme'; const btn=qs('#btnTheme'); const label=qs('#themeLabel');
    const logoL=qs('.brand-logo.brand-light'); const logoD=qs('.brand-logo.brand-dark');
    const reflect=(dark)=>{ try{ if(logoL) logoL.hidden=!!dark; if(logoD) logoD.hidden=!dark; }catch(_){ } };
    const apply=(m,{emit=true}={})=>{
      const d=(m==='dark');
      root.classList.toggle('theme-dark',d); root.classList.toggle('theme-light',!d);
      root.setAttribute('data-theme', d?'dark':'light'); body.classList.toggle('theme-dark',d); body.classList.toggle('theme-light',!d);
      try{localStorage.setItem(KEY,m)}catch(_){ }
      if(label) label.textContent=d?'Modo claro':'Modo oscuro';
      if(btn) btn.setAttribute('aria-pressed',d?'true':'false');
      reflect(d); if(emit){ try{ window.dispatchEvent(new CustomEvent('p360:theme',{detail:{mode:m,dark:d}})); }catch(_){ } P360.events.emit('theme:change',{ theme:m }); }
      log('Theme ->', m);
    };
    const current=()=>{ try{const v=localStorage.getItem(KEY); if(v==='dark'||v==='light') return v;}catch(_){ } return root.classList.contains('theme-dark')?'dark':'light'; };
    apply(current(),{emit:false});
    if (btn && !btn.dataset.bound){ btn.dataset.bound='1'; btn.addEventListener('click',()=>apply(current()==='dark'?'light':'dark')); }
    addEventListener('storage',e=>{ if(e.key===KEY && e.newValue) apply(e.newValue); });
  })();

  /* =========================
     SIDEBAR · Orquestador ÚNICO
     ========================= */
  (function sidebarOrchestrator(){
    const STATE_KEY='p360.sidebar.state'; // 'collapsed' | 'expanded'
    const LEGACY=['p360.sidebar.collapsed','p360-sidebar']; // migración
    const mqDesktop=matchMedia('(min-width: 992px)');

    const SB=()=>qs('#sidebar');

    function purgeConflicts(sb){
      if (!sb) return;
      // Clases heredadas que suelen romper la expansión:
      ['collapsed','is-collapsed','is-expanded'].forEach(c=>sb.classList.remove(c));
      // Inline styles que “congelan” el ancho:
      sb.style.width=''; sb.style.minWidth=''; sb.style.transform='';
      sb.removeAttribute('data-collapsed');
      sb.setAttribute('aria-expanded', (!isCollapsed()).toString());
    }

    function readState(){
      try{
        const v=localStorage.getItem(STATE_KEY);
        if (v==='collapsed'||v==='expanded') return v;
        // migra legacy
        for (const k of LEGACY){
          const lv = localStorage.getItem(k);
          if (lv != null){
            const nv = (lv==='1' || lv==='collapsed') ? 'collapsed' : 'expanded';
            localStorage.setItem(STATE_KEY, nv);
            LEGACY.forEach(x=>localStorage.removeItem(x));
            return nv;
          }
        }
      }catch(_){}
      return 'expanded';
    }

    function isCollapsed(){
      return root.classList.contains('sidebar-collapsed') || body.classList.contains('sidebar-collapsed');
    }

    async function ensureVisualState(){
      // Verifica que el ancho cambie entre estados; si no, hace fallback inline.
      const sb=SB(); if(!sb) return {ok:false, reason:'no-sidebar'};
      const before = sb.getBoundingClientRect().width;
      await nextFrame();
      const after  = sb.getBoundingClientRect().width;
      const ok = Math.abs(after - before) > 8; // umbral mínimo visual
      if (!ok){
        // Fallback duro basado en variables CSS
        const cs = getComputedStyle(root);
        const w  = cs.getPropertyValue('--sidebar-w').trim() || '256px';
        const wc = cs.getPropertyValue('--sidebar-w-collapsed').trim() || '72px';
        const want = isCollapsed() ? wc : w;
        sb.style.width = want; sb.style.minWidth=want;
        log('Sidebar fallback inline width →', want);
      }
      return { ok };
    }

    function applyState(state,{persist=true,emit=true,heal=true}={}){
      const collapsed = (state==='collapsed');
      // Clases globales (fuente de verdad)
      root.classList.toggle('sidebar-collapsed', collapsed);
      body.classList.toggle('sidebar-collapsed', collapsed);
      if (persist){ try{localStorage.setItem(STATE_KEY, state);}catch(_){ } }

      const sb=SB(); if(sb){ purgeConflicts(sb); }
      if (emit){ try{ window.dispatchEvent(new CustomEvent('p360:sidebar',{detail:{collapsed}})); }catch(_){ } P360.events.emit('sidebar:toggle',{ collapsed }); }
      log('Sidebar ->', collapsed ? 'collapsed' : 'expanded');

      // Opcional: autocuración si “no reacciona”
      if (heal) ensureVisualState();
    }

    // API pública
    const API = {
      state(){ return isCollapsed() ? 'collapsed' : 'expanded'; },
      expand(){ applyState('expanded'); },
      collapse(){ applyState('collapsed'); },
      toggle(){ applyState(this.state()==='collapsed' ? 'expanded' : 'collapsed'); },
      openMobile(open){
        body.classList.toggle('sidebar-open', !!open);
        qs('#sidebarBtn')?.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) this.expand();
      },
      reset(){ try{localStorage.removeItem(STATE_KEY); LEGACY.forEach(k=>localStorage.removeItem(k)); }catch(_){ } applyState('expanded'); },
      async diagnose(){
        const sb=SB(); const cs = sb ? getComputedStyle(sb): null;
        console.group('%c[P360] Sidebar Diagnose','color:#10b981;font-weight:700');
        console.log('Exists:', !!sb);
        console.log('State (class):', this.state());
        console.log('LS state:', localStorage.getItem(STATE_KEY));
        console.log('Conflicting classes present:', sb ? ['collapsed','is-collapsed','is-expanded'].filter(c=>sb.classList.contains(c)) : []);
        console.log('Inline width:', sb?.style.width || '(none)');
        if (cs){
          console.log('--sidebar-w:', getComputedStyle(root).getPropertyValue('--sidebar-w').trim());
          console.log('--sidebar-w-collapsed:', getComputedStyle(root).getPropertyValue('--sidebar-w-collapsed').trim());
          console.log('Computed width:', sb.getBoundingClientRect().width);
          console.log('Transform:', cs.transform);
          console.log('Z-Index:', cs.zIndex);
        }
        const btns = ['#sidebarBtn','#btnSidebar','#btnSidebarMobile','#sidebarToggle']
          .map(id=>({id,el:qs(id)})).filter(x=>x.el);
        console.log('Toggle buttons found:', btns.map(x=>x.id));
        console.log('Open(mobile):', body.classList.contains('sidebar-open'));
        console.groupEnd();
        return { ok: !!sb, btns: btns.map(b=>b.id) };
      },
      async selfHeal(){
        const sb=SB();
        if (!sb) { P360.toast.error('No se encontró #sidebar'); return false; }
        purgeConflicts(sb);
        // Re-aplica el estado actual para forzar estilos
        applyState(this.state(), {persist:false, emit:false, heal:false});
        const res = await ensureVisualState();
        if (!res.ok){
          P360.toast.error('Aplicado fallback inline para el ancho del sidebar.');
        } else {
          P360.toast.info('Sidebar sano ✔');
        }
        return res.ok;
      }
    };
    window.P360.sidebar = API;

    // Init: aplica estado persistido, ajusta header height y limpia restos
    function init(){
      const hdr=qs('#topbar'); if(hdr){ const set=()=>root.style.setProperty('--header-h', `${hdr.offsetHeight||56}px`); set(); new ResizeObserver(set).observe(hdr); }
      applyState(readState(),{persist:false,emit:false});
      // No dejes drawer móvil abierto en desktop
      if (matchMedia('(min-width: 992px)').matches) body.classList.remove('sidebar-open');
    }
    if (document.readyState==='loading') addEventListener('DOMContentLoaded', init); else init();

    // Botón recomendado (uno solo, moderno)
    const pro = qs('#sidebarBtn');
    if (pro && !pro.dataset.bound){
      pro.dataset.bound='1';
      let hold=false, timer=null; const HOLD=300;
      pro.addEventListener('click', ()=>{
        if (mqDesktop.matches) API.toggle();
        else API.openMobile(!body.classList.contains('sidebar-open'));
      });
      // Mantener presionado = “peek expandido”
      const start=()=>{ timer=setTimeout(()=>{ hold=true; API.expand(); }, HOLD); };
      const end=()=>{ if(timer) clearTimeout(timer); if(hold){ hold=false; /* no tocamos LS aquí */ } };
      pro.addEventListener('mousedown', start); pro.addEventListener('touchstart', start, {passive:true});
      ['mouseup','mouseleave','touchend','touchcancel'].forEach(ev=> pro.addEventListener(ev, end));
      pro.addEventListener('dblclick', ()=> API.reset()); // reset duro
    }

    // Compatibilidad: botones legacy (si existen)
    ['#btnSidebar','#sidebarToggle'].forEach(sel=>{
      const b = qs(sel); if (!b || b.dataset.bound) return;
      b.dataset.bound='1'; b.addEventListener('click', ()=> API.toggle());
    });
    const mob = qs('#btnSidebarMobile'); if (mob && !mob.dataset.bound){
      mob.dataset.bound='1'; mob.addEventListener('click', ()=> API.openMobile(!body.classList.contains('sidebar-open')));
    }

    // Backdrop/click-fuera para drawer
    document.addEventListener('click', (e)=>{
      if (!body.classList.contains('sidebar-open')) return;
      if (!e.target.closest('#sidebar') && !e.target.closest('#sidebarBtn') && !e.target.closest('#btnSidebarMobile')){
        API.openMobile(false);
      }
    });
    addEventListener('keydown', (e)=>{ if (e.key==='Escape') API.openMobile(false); });

    // Resize → cierra drawer al pasar a desktop
    addEventListener('resize', debounce(()=>{ if (innerWidth>=992) body.classList.remove('sidebar-open'); }, 120));

    // Sincronización multi-pestaña
    addEventListener('storage', (e)=>{ if (e.key===STATE_KEY && e.newValue) applyState(e.newValue,{persist:false}); });

    // Chequeo de CSS (var obligatoria)
    (function cssCheck(){
      const v = getComputedStyle(root).getPropertyValue('--sidebar-w').trim();
      if (!v){
        const msg='⚠️ Falta variable --sidebar-w (¿dashboard.css cargado?). Se activó un fallback visual mínimo.';
        console.warn('[P360]', msg); P360.toast.error(msg);
        if (!document.getElementById('p360-sidebar-fallback')){
          const s=document.createElement('style'); s.id='p360-sidebar-fallback';
          s.textContent=`:root{--header-h:60px;--sidebar-w:256px;--sidebar-w-collapsed:72px}
            #sidebar{position:fixed!important;top:var(--header-h)!important;left:0!important;width:var(--sidebar-w)!important;
              background:#0f1620;color:#e5e7eb;border-right:1px solid rgba(0,0,0,.2)!important;display:block!important;transform:none!important;z-index:9998!important}
            .admin-content, main, #p360-main{margin-left:var(--sidebar-w)!important;padding-top:calc(var(--header-h) + 12px)!important}
            html.sidebar-collapsed .admin-content, html.sidebar-collapsed main, html.sidebar-collapsed #p360-main{margin-left:var(--sidebar-w-collapsed)!important}`;
          document.head.appendChild(s);
        }
      } else {
        log('CSS OK · --sidebar-w =', v);
      }
    })();

    /* =========================
       Resaltar ítem activo en el sidebar
       ========================= */
    function normalizeUrl(u){
      try{
        const x = new URL(u, location.origin);
        x.hash=''; x.search='';
        let p = x.pathname.replace(/\/+$/,'');
        if (!p) p='/';
        return p.toLowerCase();
      }catch(_){
        return String(u||'').split('#')[0].split('?')[0].replace(/\/+$/,'').toLowerCase() || '/';
      }
    }
    function highlightActive(){
      const cur = normalizeUrl(location.href);
      const links = qsa('#sidebar a[href]');
      links.forEach(a=>{
        const href = a.getAttribute('href') || '';
        const tgt  = normalizeUrl(href);
        const exact = (tgt === cur);
        const starts = cur.startsWith(tgt) && tgt!='/';
        const active = exact || starts;
        a.classList.toggle('is-active', active);
        if (active) a.setAttribute('aria-current','page'); else a.removeAttribute('aria-current');
        const li = a.closest('li'); if (li) li.classList.toggle('is-active', active);
        const det = a.closest('details'); if (det && active) det.open = true;
      });
    }
    // Exponer y ejecutar inicialmente
    window.P360.highlightActive = highlightActive;
    highlightActive();
    // Recalcar activo cuando cambie el historial (PJAX empuja estado)
    addEventListener('popstate', ()=>{ try{ highlightActive(); }catch(_){ } });

  })();

  /* =========================
     BÚSQUEDA RÁPIDA
     ========================= */
  (function quickSearch(){
    const input=qs('#globalSearch'); if(!input) return;
    const focus=()=>{ input.focus(); input.select?.(); };
    addEventListener('keydown', e=>{
      const k=(e.key||'').toLowerCase(), mod=e.ctrlKey||e.metaKey;
      if(mod && k==='k'){ e.preventDefault(); focus(); }
      else if(!mod && !e.altKey && !e.shiftKey && k==='/'){
        const a=document.activeElement, typing=a && (a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.isContentEditable);
        if(!typing){ e.preventDefault(); focus(); }
      }
    }, {passive:false});
  })();

  /* =========================
     MENÚS <details> del header
     ========================= */
  (function detailsMenus(){
    const all=qsa('details[data-menu]');
    all.forEach(d=>{
      if (!d.dataset.bound){
        d.dataset.bound='1';
        d.addEventListener('toggle', ()=>{ if (d.open) all.forEach(x=>{ if (x!==d) x.open=false; }); });
      }
    });
    document.addEventListener('click', (e)=>{ if (!e.target.closest('details')) all.forEach(x=> x.open=false); });
    addEventListener('keydown', (e)=>{ if (e.key==='Escape') all.forEach(x=> x.open=false); });
  })();

  /* =========================
     HEADER sombra al scroll
     ========================= */
  (function headerShadow(){
    const h=qs('.header');
    const on=()=> h && h.classList.toggle('header--shadow', window.scrollY > 6);
    addEventListener('scroll', on, {passive:true}); on();
  })();

  /* =========================
     FORM AJAX (opcional)
     ========================= */
  document.addEventListener('submit', async (e)=>{
    const f = e.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (!f.matches('[data-ajax-json]')) return;
    e.preventDefault();
    const action = f.action || location.href;
    const method = (f.method||'POST').toUpperCase();
    const data = Object.fromEntries(new FormData(f).entries());
    const btn = f.querySelector('[type="submit"]'); const prev = btn ? btn.disabled : false;
    if (btn) btn.disabled = true;
    try{
      const res = await P360.http.fetch(action, { method, json:data });
      f.dispatchEvent(new CustomEvent('ajax:ok', { detail: res })); P360.toast.info('Guardado correctamente');
    } catch(err){
      f.dispatchEvent(new CustomEvent('ajax:error', { detail: err })); P360.toast.error('Error al enviar el formulario');
      console.error('[P360] AJAX form error:', err);
    } finally { if (btn) btn.disabled = prev; }
  });

  /* =========================
     HUD de depuración (Ctrl+Shift+D)
     ========================= */
  function showDebug(){
    const sb=document.getElementById('sidebar');
    const cs=sb?getComputedStyle(sb):null;
    const el=document.getElementById('p360-debug')||document.createElement('div');
    el.id='p360-debug';
    el.style.cssText='position:fixed;left:12px;bottom:12px;z-index:10001;background:rgba(0,0,0,.72);color:#fff;padding:8px 10px;border-radius:8px;font:12px/1.2 system-ui';
    el.innerHTML=`
      <div><b>Theme:</b> ${root.classList.contains('theme-dark')?'dark':'light'}</div>
      <div><b>State:</b> ${P360.sidebar ? P360.sidebar.state() : 'n/a'}</div>
      <div><b>sidebar-collapsed:</b> ${root.classList.contains('sidebar-collapsed')?'yes':'no'}</div>
      <div><b>#sidebar:</b> ${sb?'OK':'NO DOM'}</div>
      <div><b>width:</b> ${sb?sb.getBoundingClientRect().width:'n/a'}px</div>
      <div><b>transform:</b> ${cs?cs.transform:'n/a'}</div>`;
    if(!el.parentNode) document.body.appendChild(el);
    setTimeout(()=>{ try{ el.remove(); }catch(_){ } }, 6000);
  }
  addEventListener('keydown', (e)=>{ if (e.ctrlKey && e.shiftKey && e.key.toLowerCase()==='d'){ showDebug(); } });

  // Señales ready
  try{ window.dispatchEvent(new CustomEvent('p360:ui:ready')); }catch(_){ }
  P360.log('UI listo ✔  —  Prueba en consola: P360.sidebar.toggle(), P360.sidebar.diagnose(), P360.sidebar.selfHeal()');
})();
</script>

<!-- =============================================================================
     SPA-lite (PJAX): navegación en el mismo dashboard
     ============================================================================= -->
<style>
  #p360-progress{position:fixed;left:0;top:0;height:3px;width:0;background:#6366f1;z-index:99999;transition:width .25s ease,opacity .25s ease;opacity:0}
  #p360-progress.show{opacity:1}
</style>
<script>
(function(){
  'use strict';
  const { qs } = P360.util; const log=(...a)=>P360.log('PJAX',...a);

  // Barra de progreso
  let bar=document.getElementById('p360-progress');
  if(!bar){ bar=document.createElement('div'); bar.id='p360-progress'; document.body.appendChild(bar); }
  let t=null;
  const start=()=>{ clearTimeout(t); bar.style.width='0%'; bar.classList.add('show'); requestAnimationFrame(()=>bar.style.width='30%'); };
  const pulse =()=>{ bar.style.width='60%'; };
  const done =()=>{ bar.style.width='100%'; t=setTimeout(()=>{ bar.classList.remove('show'); bar.style.width='0%'; }, 220); };

  // Selectores regiones
  const SEL_MAIN='#p360-main, main.admin-content, main[role="main"]';
  const SEL_HEADER='#page-header, .page-header, [data-region="page-header"]';

  const sameOrigin=(u)=>P360.url.isSameOrigin(u);
  const isAdmin   =(u)=>P360.url.isAdminPath(u);
  function shouldPJAX(a){
    if(!a) return false;
    if(a.hasAttribute('download')) return false;
    if(a.getAttribute('target')==='_blank') return false;
    const href=a.getAttribute('href')||'';
    if(!href || href[0]==='#') return false;
    if(!sameOrigin(href) || !isAdmin(href)) return false;
    if(a.matches('[data-no-pjax]')) return false;
    return true;
  }

  // Extrae contenido de la respuesta completa (HTML)
  function extract(html){
    const doc=new DOMParser().parseFromString(html,'text/html');
    const title=doc.querySelector('title')?.textContent || document.title;
    const main =doc.querySelector(SEL_MAIN);
    const headr=doc.querySelector(SEL_HEADER);
    return { title, mainHTML: main?main.innerHTML:null, headerHTML: headr?headr.innerHTML:null };
  }
  // Ejecuta scripts inline reinyectados
  function execInline(container){
    Array.from(container.querySelectorAll('script')).filter(s=>!s.src && (!s.type || s.type==='text/javascript')).forEach(old=>{
      const s=document.createElement('script'); s.textContent=old.textContent; document.body.appendChild(s); s.remove();
    });
  }
  function swap(p,url){
    const main=document.querySelector(SEL_MAIN); const head=document.querySelector(SEL_HEADER);
    if(!main){ log('No hay main para PJAX'); return false; }
    if(p.headerHTML!==null && head) head.innerHTML=p.headerHTML;
    if(p.mainHTML!==null){ main.innerHTML=p.mainHTML; execInline(main); }
    document.title=p.title; window.scrollTo({top:0, behavior:'smooth'});
    try{ P360.events.emit('pjax:after',{url}); }catch(_){ }
    try{ window.dispatchEvent(new CustomEvent('p360:pjax:after',{detail:{url}})); }catch(_){ }
    try{ P360.highlightActive && P360.highlightActive(); }catch(_){ }
    return true;
  }

  async function go(url,{push=true}={}){
    try{
      start();
      const res=await fetch(url,{headers:{'X-P360-PJAX':'1','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
      pulse(); if(!res.ok) throw new Error('HTTP '+res.status);
      const html=await res.text(); const payload=extract(html);
      const ok=swap(payload,url); if(!ok){ location.href=url; return; }
      if(push) history.pushState({url,p360pjax:true}, payload.title, url);
      log('OK ->', url);
    }catch(err){
      log('Error ->', err); P360.toast?.error('No se pudo cargar la página, redirigiendo…'); location.href=url;
    }finally{ done(); }
  }
  // Exponer por consola si se requiere
  window.P360 = window.P360 || {}; window.P360.pjax = { go };

  // Intercepta clicks
  document.addEventListener('click', (e)=>{
    const a=e.target.closest && e.target.closest('a[href]'); if(!a) return;
    if(e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button===1) return;
    if(!shouldPJAX(a)) return; e.preventDefault(); go(a.getAttribute('href'), {push:true});
  }, true);

  // Popstate
  window.addEventListener('popstate', (e)=>{ if(e.state && e.state.p360pjax) go(location.href,{push:false}); });

  // Formularios PJAX (opt-in via [data-pjax-form])
  document.addEventListener('submit', async (e)=>{
    const f=e.target; if(!(f instanceof HTMLFormElement) || !f.matches('[data-pjax-form]')) return;
    e.preventDefault();
    const action=f.action||location.href; const method=(f.method||'POST').toUpperCase();
    const fd=new FormData(f); const opts={method, body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}};
    if(method==='GET'){ const u=new URL(action, location.href); fd.forEach((v,k)=>u.searchParams.set(k,v)); return go(u.toString(),{push:true}); }
    start();
    try{
      const res=await fetch(action, opts); const html=await res.text(); const p=extract(html);
      const ok=swap(p, action); if(!ok){ location.href=action; return; }
      history.pushState({url:action,p360pjax:true}, p.title, action);
      P360.toast?.success('Guardado correctamente');
    }catch(err){ console.error('[P360][PJAX] Form error:', err); P360.toast?.error('Error al enviar el formulario'); }
    finally{ done(); }
  });

  try{ P360.events.emit('pjax:ready'); }catch(_){ } try{ window.dispatchEvent(new CustomEvent('p360:pjax:ready')); }catch(_){ }
  log('listo');
})();
</script>

<!-- Carga diferida de módulos propios -->
<script defer src="{{ asset('assets/admin/js/dashboard.js') }}"></script>
<script>
  // NovaBot (lazy)
  (function () {
    function loadNova() {
      var s = document.createElement('script');
      s.src = "{{ asset('assets/admin/js/novabot.js') }}";
      s.defer = true;
      s.onload = function(){ try{ P360.log('NovaBot listo'); }catch(_){ } };
      document.head.appendChild(s);
    }
    if ('requestIdleCallback' in window) requestIdleCallback(loadNova, { timeout: 1500 });
    else setTimeout(loadNova, 600);
  })();
</script> 

<script>
/* ====== P360 · View Asset Loader (CSS por-vista + PJAX) ====== */
(function () {
  'use strict';
  const H = document.head;

  function haveCss(href){
    return !!document.querySelector(`link[rel="stylesheet"][data-href="${href}"], link[rel="stylesheet"][href="${href}"]`);
  }
  function loadCss(href){
    return new Promise((ok, bad)=>{
      if (!href) return ok();
      if (haveCss(href)) return ok();
      const l = document.createElement('link');
      l.rel = 'stylesheet';
      l.href = href;
      l.setAttribute('data-href', href);
      l.onload = ()=> ok();
      l.onerror = ()=> bad(new Error('CSS load fail: '+href));
      H.appendChild(l);
    });
  }

  function getRequireList(container){
    // Acepta 1 o varias URLs separadas por coma
    const holder = container.querySelector('[data-require-css]');
    const list = holder ? (holder.getAttribute('data-require-css')||'') : '';
    return list.split(',').map(s=>s.trim()).filter(Boolean);
  }

  async function ensureAssets(container){
    const cssList = getRequireList(container);
    await Promise.all(cssList.map(loadCss));
  }

  // Carga inicial
  addEventListener('DOMContentLoaded', ()=> ensureAssets(document));

  // Tras navegación PJAX (ya la disparas en tu orquestador)
  addEventListener('p360:pjax:after', ()=>{
    const main = document.querySelector('#p360-main') || document;
    ensureAssets(main);
  });

  // expón para debugging manual: P360.ensureAssetsFrom(document)
  window.P360 = window.P360 || {};
  window.P360.ensureAssetsFrom = ensureAssets;
})();
</script>

