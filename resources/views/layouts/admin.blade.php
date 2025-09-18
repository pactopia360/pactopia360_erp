{{-- resources/views/layouts/admin.blade.php --}}
@php
  // ===== Título y tema =====
  $pageTitle      = trim($__env->yieldContent('title')) ?: 'Panel Administrativo · Pactopia360';
  $bodyTheme      = session('ui.theme', 'light'); // 'light' | 'dark'
  $htmlThemeClass = $bodyTheme === 'dark' ? 'theme-dark' : 'theme-light';

  // ===== Rutas de CSS con cache-busting (si existen) =====
  $minSize   = 16; // ignora archivos vacíos
  $BASE_ABS  = public_path('assets/admin/css/base.css');
  $UI_ABS    = public_path('assets/admin/css/ui.css');
  $APP_ABS   = public_path('assets/admin/css/app.css');
  $SB_ABS    = public_path('assets/admin/css/sidebar.css');
  $FRAME_ABS = public_path('assets/admin/css/frame.css');

  $BASE_URL  = (is_file($BASE_ABS)  && filesize($BASE_ABS)  > $minSize) ? asset('assets/admin/css/base.css')   .'?v='.filemtime($BASE_ABS)  : null;
  $UI_URL    = (is_file($UI_ABS)    && filesize($UI_ABS)    > $minSize) ? asset('assets/admin/css/ui.css')     .'?v='.filemtime($UI_ABS)    : null;
  $APP_URL   = (is_file($APP_ABS)   && filesize($APP_ABS)   > $minSize) ? asset('assets/admin/css/app.css')    .'?v='.filemtime($APP_ABS)   : null;
  $FRAME_URL = (is_file($FRAME_ABS) && filesize($FRAME_ABS) > $minSize) ? asset('assets/admin/css/frame.css')  .'?v='.filemtime($FRAME_ABS) : null;

  // Skin principal (usa el primero disponible)
  $SKIN_URL  = $BASE_URL ?: ($UI_URL ?: $APP_URL);
@endphp

<!DOCTYPE html>
<html lang="es" class="h-100 {{ $htmlThemeClass }}" data-theme="{{ $bodyTheme }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ $pageTitle }}</title>

    {{-- ===== Critical CSS (tokens + estructura + utilidades) ===== --}}
    <style id="p360-critical-tokens">
      :root{
        /* Paleta / marca */
        --brand-red:#e11d48; --brand-navy:#0b2030; --brand-slate:#323642; --brand-gray:#a3a3a3;

        /* Base */
        --bg:#f6f7f9; --text:#0f172a; --muted:#6b7280;
        --card-bg:#ffffff; --card-border:rgba(0,0,0,.08); --panel-bg:#f8fafc;

        /* Acentos */
        --accent:#0e2a3b; --accent-2:#8b5cf6; --accent-3:#06b6d4;

        /* Estados */
        --info:#3b82f6; --success:#16a34a; --warning:#f59e0b; --danger:#ef4444;

        /* Topbar + Sidebar */
        --topbar-bg:#ffffff; --topbar-fg:#0f172a; --topbar-border:rgba(0,0,0,.08);
        --topbar-accent:var(--accent);
        --sb-bg:var(--topbar-bg); --sb-fg:var(--topbar-fg); --sb-border:var(--topbar-border);
        --sb-hover:color-mix(in oklab, var(--topbar-accent) 12%, transparent);
        --sb-active-bg:color-mix(in oklab, var(--topbar-accent) 22%, transparent);
        --sb-active-fg:color-mix(in oklab, #fff 92%, var(--topbar-fg));
        --sb-indicator:var(--topbar-accent);

        /* Layout */
        --container-max:1280px; --header-h:56px;
        --radius-sm:10px; --radius-md:12px; --radius-lg:14px;
        --shadow-1:0 2px 8px rgba(0,0,0,.06); --shadow-2:0 10px 28px rgba(0,0,0,.12);
        --safe-bottom: env(safe-area-inset-bottom, 0px);
      }
      [data-theme="dark"]{
        --bg:#0b1220; --text:#e5e7eb; --muted:#9ca3af;
        --card-bg:#0f172a; --card-border:rgba(255,255,255,.08); --panel-bg:#0c172b;

        --topbar-bg:#0b1220; --topbar-fg:#e5e7eb; --topbar-border:rgba(255,255,255,.08);
        --topbar-accent:#7ba1ff;

        --sb-bg:var(--topbar-bg); --sb-fg:var(--topbar-fg); --sb-border:var(--topbar-border);
        --sb-hover:color-mix(in oklab, var(--topbar-accent) 16%, transparent);
        --sb-active-bg:color-mix(in oklab, var(--topbar-accent) 28%, transparent);
        --sb-active-fg:#ffffff; --sb-indicator:var(--topbar-accent);
      }

      /* ===== Estructura base ===== */
      html,body{height:100%}
      *,*::before,*::after{ box-sizing:border-box }
      body{
        min-height:100vh; display:flex; flex-direction:column; overflow:hidden;
        background:var(--bg); color:var(--text);
        -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
      }

      /* Topbar fijo */
      .p360-topbar{
        position:fixed; inset:0 0 auto 0; height:var(--header-h);
        background:var(--topbar-bg); color:var(--topbar-fg);
        border-bottom:1px solid var(--topbar-border);
        z-index:1100; display:flex; align-items:center;
      }
      .p360-topbar > *{width:100%}

      /* App shell */
      .admin-app{
        flex:1 1 auto; min-height:0; display:flex; overflow:hidden; padding-top:var(--header-h);
      }
      .admin-content{
        flex:1 1 auto; min-height:0; overflow:auto; -webkit-overflow-scrolling:touch;
        background:var(--bg);
        display:flex; flex-direction:column; /* footer al fondo */
      }
      .page-container{ padding:.75rem; flex:1 1 auto }
      .page-shell{ width:100%; max-width:var(--container-max); margin-inline:auto }

      /* Footer fijo al fondo del contenido */
      .admin-footer{
        margin-top:auto; border-top:1px solid var(--card-border); background:var(--bg); padding-bottom:var(--safe-bottom)
      }
      .admin-footer .footer-inner{
        max-width:var(--container-max); margin-inline:auto; padding:14px 16px;
        color:var(--muted); font-size:12px;
        display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
      }
      .admin-footer .meta a{ color:inherit; text-decoration:underline; opacity:.9; margin-left:12px }
      [data-theme="dark"] .admin-footer{ border-top-color:rgba(255,255,255,.10) }

      /* Page header sticky */
      #page-header{
        position:sticky; top:0; z-index:5;
        background:color-mix(in oklab, var(--card-bg) 96%, transparent);
        backdrop-filter:saturate(120%) blur(6px);
        border-bottom:1px solid var(--card-border)
      }
      .affix-shadow{ box-shadow:0 8px 24px rgba(0,0,0,.08) }

      /* Widgets globales */
      #p360-progress{ position:fixed; left:0; top:0; height:3px; width:0%; background:linear-gradient(90deg,var(--brand-red),var(--topbar-accent)); z-index:1300; transition:width .2s ease, opacity .3s ease; box-shadow:0 2px 8px rgba(0,0,0,.2) }
      #p360-loading{ position:fixed; inset:0; display:none; place-items:center; background:rgba(0,0,0,.25); z-index:1250; backdrop-filter: blur(2px) }
      #p360-loading .spinner{ width:56px; height:56px; border-radius:50%; border:6px solid #fff; border-top-color:transparent; animation:spin 1s linear infinite }
      @keyframes spin{ to{ transform:rotate(360deg) } }
      #p360-alerts{ position:fixed; right:16px; bottom:calc(16px + var(--safe-bottom)); display:flex; flex-direction:column; gap:8px; z-index:1400 }
      .toast{ background:#111827; color:#fff; border-radius:12px; padding:10px 12px; box-shadow:var(--shadow-2); display:flex; align-items:center; gap:10px; max-width:min(92vw, 520px) }
      .toast.info{ background:color-mix(in oklab, var(--info) 86%, #000) } .toast.success{ background:color-mix(in oklab, var(--success) 86%, #000) } .toast.warn{ background:color-mix(in oklab, var(--warning) 86%, #000) } .toast.error{ background:color-mix(in oklab, var(--danger) 86%, #000) }
      .toast .x{ background:transparent;border:0;color:#fff;cursor:pointer;font-weight:700 }

      #p360-cmd{ position:fixed; inset:0; display:none; place-items:center; z-index:1400; background:rgba(0,0,0,.35) }
      #p360-cmd .cmd-card{ width:min(720px, 92vw); background:var(--card-bg); color:var(--text);
        border:1px solid var(--card-border); border-radius:14px; box-shadow:var(--shadow-2); padding:12px }
      #p360-cmd input{ width:100%; border:1px solid var(--card-border); border-radius:10px; padding:10px 12px; outline:0; background:var(--panel-bg); color:var(--text) }

      /* Accesibilidad */
      .skiplink{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
      .skiplink:focus{ left:12px; top:12px; width:auto; height:auto; padding:8px 10px; background:#111827; color:#fff; border-radius:8px; z-index:1600; box-shadow:0 6px 18px rgba(0,0,0,.2) }

      /* Densidad compacta (opt-in) */
      html[data-density="compact"]{ --radius-sm:8px; --radius-md:10px; --radius-lg:12px }
      html[data-density="compact"] .page-container{ padding:.6rem }

      /* Si algún widget dibuja legal en TOPBAR, no lo mostramos ahí */
      .p360-topbar .topbar-copy, .p360-topbar .copyright, .p360-topbar .legal, .p360-topbar [data-copyright]{ display:none!important }

      @media (prefers-reduced-motion: reduce){ *{transition:none!important;animation:none!important;scroll-behavior:auto!important} }
      @media (forced-colors: active){ .p360-topbar{ border-bottom:1px solid CanvasText } }
      @media print{ .p360-topbar, #sidebar, #p360-alerts, #p360-progress{ display:none !important } .admin-app{ padding-top:0 } }
    </style>

    {{-- Kill-switch CSS extra para el topbar (por si terceros inyectan cosas) --}}
    <style id="p360-legal-fix">
      /* Nuke general en topbar */
      #p360-topbar small,
      #p360-topbar .copyright,
      #p360-topbar .legal,
      #p360-topbar [data-copyright],
      #topbar       small,
      #topbar       .copyright,
      #topbar       .legal,
      #topbar       [data-copyright],
      #p360-topbar #p360-copy,
      #topbar       #p360-copy { display:none!important; }

      /* Enlaces comunes (por texto o ruta) si caen en el topbar */
      #p360-topbar a[href*="prefer"],
      #p360-topbar a[href*="privac"],
      #p360-topbar a[href*="/config"],
      #topbar       a[href*="prefer"],
      #topbar       a[href*="privac"],
      #topbar       a[href*="/config"] { display:none!important; }
    </style>

    {{-- ===== Skin / Frame globales ===== --}}
    @if ($SKIN_URL)  <link id="css-admin-skin"  rel="stylesheet" href="{{ $SKIN_URL  }}">@endif
    @if ($FRAME_URL) <link id="css-admin-frame" rel="stylesheet" href="{{ $FRAME_URL }}">@endif
    @stack('styles')
  </head>

  <body class="d-flex flex-column min-vh-100">
    <a href="#p360-main" class="skiplink">Saltar al contenido</a>

    {{-- Header fijo (wrapper) --}}
    <header id="p360-topbar" class="p360-topbar" role="banner" aria-label="Barra superior">
      @includeIf('layouts.partials.header')
    </header>

    {{-- App Shell --}}
    <div id="admin-app" class="admin-app">
      @includeIf('layouts.partials.sidebar')

      <main id="p360-main" class="admin-content" role="main" aria-live="polite">
        @hasSection('page-header')
          <header id="page-header" class="page-header">@yield('page-header')</header>
        @endif

        @if (session('status'))
          <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
          </div>
        @endif

        {{-- Contenido principal --}}
        <div class="page-container">@yield('content')</div>

        {{-- FOOTER real al fondo --}}
        <footer class="admin-footer">
          <div class="footer-inner">
            <div class="meta" id="footer-left">
              <small class="legal" id="p360-copy">© {{ date('Y') }} Pactopia360 — Todos los derechos reservados.</small>
              @if(config('app.version')) <small>v{{ config('app.version') }}</small> @endif
            </div>
            <div class="meta" id="footer-right">
              @php $hasConfig = \Illuminate\Support\Facades\Route::has('admin.config.index'); @endphp
              <small><a href="{{ $hasConfig ? route('admin.config.index') : '#' }}">Preferencias</a></small>
              <small><a href="#">Privacidad</a></small>
            </div>
          </div>
        </footer>
      </main>
    </div>

    {{-- Widgets globales --}}
    <div id="p360-progress" aria-hidden="true"></div>
    <div id="p360-loading" role="status" aria-live="polite" aria-label="Cargando"><div class="spinner"></div></div>
    <div id="p360-alerts" aria-live="polite"></div>
    <div id="p360-cmd"><div class="cmd-card"><input type="search" id="p360-cmd-input" placeholder="Escribe para buscar…  (Esc para cerrar)"></div></div>

    {{-- Sidebar.css (post-paint) --}}
    @if (is_file($SB_ABS) && filesize($SB_ABS) > $minSize)
      <link id="css-sidebar" rel="stylesheet" href="{{ asset('assets/admin/css/sidebar.css').'?v='.filemtime($SB_ABS) }}">
    @endif

    {{-- ===== JS utilitario global (sin dependencias) ===== --}}
    <script>
    (function(){
      'use strict';

      const root     = document.documentElement;
      const main     = document.getElementById('p360-main');
      const progress = document.getElementById('p360-progress');
      const loading  = document.getElementById('p360-loading');
      const alerts   = document.getElementById('p360-alerts');
      const cmd      = document.getElementById('p360-cmd');
      const cmdIn    = document.getElementById('p360-cmd-input');

      // ===== Guard: ejecutar solo una vez por bandera =====
      window.p360 = window.p360 || {};
      window.p360.once = function(flag, cb){
        const k = '__p360_'+flag;
        if (window[k]) return false;
        window[k] = 1;
        try{ cb && cb(); }catch(e){ console.error(e); }
        return true;
      };

      // ===== API global P360 =====
      window.P360 = {
        setTheme(t){
          if(!t) return;
          root.dataset.theme = t;
          const dark = (t === 'dark');
          root.classList.toggle('theme-dark', dark);
          root.classList.toggle('theme-light', !dark);
          try{ localStorage.setItem('p360.theme', t); }catch(_){}
        },
        toggleTheme(){ P360.setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'); },
        setDensity(d){
          if (d !== undefined) {
            d ? root.setAttribute('data-density', d) : root.removeAttribute('data-density');
            try{ localStorage.setItem('p360.density', d || ''); }catch(_){}
          }
        },
        toggleDensity(){ P360.setDensity(root.getAttribute('data-density') === 'compact' ? '' : 'compact'); },
        toast(msg, opts={}){
          const el = document.createElement('div');
          el.className = 'toast ' + (opts.type || 'info');
          el.innerHTML = '<div style="display:flex;gap:8px;align-items:center">'
            + (opts.icon || '') + '<span>' + (msg || '') + '</span></div>'
            + '<button class="x" aria-label="Cerrar">×</button>';
          alerts && alerts.appendChild(el);
          const close = ()=>{ el.style.opacity='0'; setTimeout(()=>el.remove(), 240); };
          el.querySelector('.x').onclick = close;
          setTimeout(close, opts.timeout || 4000);
        },
        loading: { show(){ loading && (loading.style.display='grid'); }, hide(){ loading && (loading.style.display='none'); } },
        progress: {
          start(){ if(!progress) return; progress.style.opacity='1'; progress.style.width='25%'; tick(); },
          done(){ if(!progress) return; progress.style.width='100%'; setTimeout(()=>{progress.style.opacity='0';progress.style.width='0%';}, 250); }
        },
        focusSearch(){
          const el = document.querySelector('#globalSearch,#global-search, form[role="search"] input[type="search"], input[type="search"][name="q"]');
          if (el){ el.focus(); el.select && el.select(); }
        },
        openCmd(){ if(!cmd) return; cmd.style.display='grid'; if(cmdIn){ cmdIn.value=''; setTimeout(()=>cmdIn.focus(),10); } },
        closeCmd(){ if(!cmd) return; cmd.style.display='none'; }
      };

      function tick(){
        if(!progress || progress.style.opacity!=='1') return;
        const w = parseFloat(progress.style.width) || 0;
        progress.style.width = Math.min(w + Math.random()*18, 90) + '%';
        setTimeout(tick, 180);
      }

      // ===== Altura real del Topbar → CSS var =====
      function syncHeaderHeight(){
        const tb = document.getElementById('p360-topbar') || document.getElementById('topbar');
        if(!tb) return;
        const h = Math.max(48, Math.round(tb.getBoundingClientRect().height));
        root.style.setProperty('--header-h', h + 'px');
      }
      addEventListener('load', ()=>requestAnimationFrame(syncHeaderHeight), {once:true});
      addEventListener('resize', ()=>requestAnimationFrame(syncHeaderHeight));
      setTimeout(syncHeaderHeight, 160);

      // ===== Persistencia (tema / densidad) =====
      try{
        const th = localStorage.getItem('p360.theme');   if(th) P360.setTheme(th);
        const dn = localStorage.getItem('p360.density'); if(dn) P360.setDensity(dn);
      }catch(_){}

      // ===== Atajos de teclado =====
      addEventListener('keydown', (e)=>{
        const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;
        if (ctrl && e.key.toLowerCase()==='k'){ e.preventDefault(); P360.focusSearch(); }
        if (e.key==='/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)){ e.preventDefault(); P360.focusSearch(); }
        if (ctrl && e.key.toLowerCase()==='p'){ e.preventDefault(); P360.openCmd(); }
        if (e.key==='Escape' && cmd && cmd.style.display==='grid'){ e.preventDefault(); P360.closeCmd(); }
      });
      cmd && cmd.addEventListener('click', (ev)=>{ if(ev.target===cmd) P360.closeCmd(); });

      // ===== Sombra en header de página =====
      main && main.addEventListener('scroll', ()=>{
        const ph = document.getElementById('page-header');
        if(!ph) return; ph.classList.toggle('affix-shadow', main.scrollTop > 6);
      }, {passive:true});

      // ===== Restauración de scroll por ruta =====
      const keyScroll = ()=> 'p360.scroll.' + (location.pathname || '');
      addEventListener('beforeunload', ()=>{ try{ main && sessionStorage.setItem(keyScroll(), String(main.scrollTop||0)); }catch(_){ }});
      addEventListener('load', ()=>{ try{ const y = parseInt(sessionStorage.getItem(keyScroll())||'0',10)||0; main && main.scrollTo({top:y}); }catch(_){ }});

      // ===== Limpieza de assets de vista =====
      function cleanupViewAssets(){
        ['css-home','css-usuarios','css-perfiles','css-crud-module'].forEach(id=>{ const el = document.getElementById(id); el && el.remove(); });
        document.querySelectorAll('#p360-main script[data-p360-script]').forEach(s=>s.remove());
      }

      // ===== Hooks PJAX (si los usas) =====
      addEventListener('p360:pjax:before', ()=>{
        try{ main && sessionStorage.setItem(keyScroll(), String(main.scrollTop||0)); }catch(_){}
        P360.progress.start();
        P360.loading.show();
        cleanupViewAssets();
        document.body.classList.remove('sidebar-open');
        main && (main.style.opacity = '.001');
      });
      addEventListener('p360:pjax:response', (ev)=>{
        const d = ev && ev.detail || {};
        if (d.status && d.status >= 400 && d.url) { location.assign(d.url); }
      });
      addEventListener('p360:pjax:after', ()=>{
        requestAnimationFrame(()=>{
          P360.loading.hide();
          P360.progress.done();
          syncHeaderHeight();
          main && (main.style.opacity = '');
        });
        try{
          const y = parseInt(sessionStorage.getItem(keyScroll())||'0',10)||0;
          main && main.scrollTo({top:y});
        }catch(_){}
        const txt = (main && main.textContent || '').slice(0, 20000);
        if (/Ignition|Whoops|exceptionAsMarkdown/.test(txt)) location.reload();
      });

      // ===== LEGAL ENFORCER v3: mueve/borra legal del header y clona enlaces al footer =====
      (function legalEnforcerV3(){
        const HDR_Q = ['#p360-topbar', '#topbar', 'header.header', 'header[role="banner"]'];
        const headEls  = () => HDR_Q.flatMap(q => Array.from(document.querySelectorAll(q)));
        const inHeader = (el) => !!el && headEls().some(h => h.contains(el));
        const $ = (s, c=document) => c.querySelector(s);
        const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));

        function ensureFooterBuckets(){
          const inner = document.querySelector('.admin-footer .footer-inner');
          if(!inner) return {};
          let left  = $('#footer-left')  || ( () => { const d=document.createElement('div'); d.id='footer-left';  d.className='meta'; inner.prepend(d); return d; } )();
          let right = $('#footer-right') || ( () => { const d=document.createElement('div'); d.id='footer-right'; d.className='meta'; inner.append(d);   return d; } )();
          return {left, right};
        }

        function moveIfInHeader(el, dest){
          if(el && inHeader(el) && dest){ try{ dest.appendChild(el); }catch(_){ } }
        }

        function killTextNodes(container){
          const rx=/©|Pactopia|Pactopia360|derechos\s+reservados/i;
          const tw=document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
            acceptNode(n){ return rx.test(n.nodeValue||'') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT; }
          });
          const rm=[]; while(tw.nextNode()) rm.push(tw.currentNode);
          rm.forEach(n=>{ n.parentNode && n.parentNode.removeChild(n); });
        }

        function sanitizeHeaderRight(){
          headEls().forEach(hdr=>{
            $$('.header-right .meta, .header-right small, .header-right .legal, .header-right .copyright, .header-right [data-copyright]', hdr)
              .forEach(el=>{ el.remove(); });
            Array.from(($('.header-right', hdr)?.childNodes||[])).forEach(n=>{ if(n.nodeType===3) n.remove(); });
            $$('.header-right a', hdr).forEach(a=>{
              const t=(a.textContent||'').trim().toLowerCase();
              if(t.includes('preferencias') || t.includes('privacidad')) a.remove();
            });
            killTextNodes(hdr);
          });
        }

        function sweep(){
          const {left, right} = ensureFooterBuckets();
          const legal = $('#p360-copy');  if(legal){ moveIfInHeader(legal, left||document.body); }

          headEls().forEach(hdr=>{
            $$('a', hdr).forEach(a=>{
              const txt=(a.textContent||'').trim().toLowerCase();
              const href=(a.getAttribute('href')||'').toLowerCase();
              const isPrefs = txt.includes('preferencias') || href.includes('config') || href.includes('prefer');
              const isPriv  = txt.includes('privacidad')   || href.includes('privac');
              if(isPrefs || isPriv){
                if(right && href && !right.querySelector(`a[href="${href}"]`)){
                  const wrap=document.createElement('small'); const c=a.cloneNode(true); c.removeAttribute('id'); wrap.appendChild(c); right.appendChild(wrap);
                }
                a.remove();
              }
            });
            sanitizeHeaderRight();
          });
        }

        sweep();
        try{
          const mo = new MutationObserver((muts)=>{
            if(muts.some(m => headEls().some(h=>h.contains(m.target)) || Array.from(m.addedNodes||[]).some(n=> headEls().some(h=> h.contains?.(n))))){
              sweep();
            }
          });
          mo.observe(document.documentElement, {childList:true, subtree:true, characterData:true});
          addEventListener('p360:pjax:after', sweep);
          addEventListener('load', sweep);
        }catch(_){}
      })();

      // ===== HUD + perf mínimos =====
      let hud;
      function toggleHud(){
        if(!hud){
          hud = document.createElement('div');
          hud.style.cssText = 'position:fixed;right:12px;bottom:12px;background:var(--card-bg);color:var(--text);border:1px solid var(--card-border);border-radius:12px;box-shadow:var(--shadow-2);padding:10px;z-index:1500;max-width:min(92vw,560px);font:12px/1.35 ui-monospace,monospace';
          hud.innerHTML = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><strong>HUD</strong><button id="hudx" style="margin-left:auto;border:0;background:transparent;cursor:pointer;font-weight:800">×</button></div><div id="hudc"></div>';
          document.body.appendChild(hud); document.getElementById('hudx').onclick=()=>hud.remove();
        }
        const c = hud.querySelector('#hudc');
        c.textContent = 'theme='+(root.dataset.theme||'light')
          +' | density='+(root.getAttribute('data-density')||'default')
          +' | header-h='+getComputedStyle(root).getPropertyValue('--header-h').trim();
      }
      addEventListener('keydown', (e)=>{ if(e.altKey && !e.ctrlKey && !e.shiftKey && e.key.toLowerCase()==='d'){ e.preventDefault(); toggleHud(); }});

      (function perf(){ try{
        const t = performance.timing; const tt = Math.max(0, t.domComplete - t.navigationStart);
        if(tt) console.log('%cP360','background:#111;color:#fff;padding:2px 6px;border-radius:6px','domComplete='+tt+'ms');
      }catch(_){}})();

    })();
    </script>

    @stack('scripts')
    @yield('scripts')

    <noscript>
      <div style="position:fixed;left:0;right:0;top:0;background:#b91c1c;color:#fff;padding:.5rem 1rem;z-index:99999;text-align:center">
        Para usar el panel correctamente, habilita JavaScript.
      </div>
    </noscript>

    {{-- Burbujas flotantes (bots, etc.) a lugar seguro --}}
    <style>
      .novabot-floating, #novabot-widget, .chat-bubble, [data-novabot]{
        position:fixed !important; right:16px !important; bottom:calc(84px + var(--safe-bottom)) !important; z-index:1200 !important;
      }
    </style>
  </body>
</html>
