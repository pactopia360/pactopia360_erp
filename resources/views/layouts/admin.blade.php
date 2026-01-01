{{-- resources/views/layouts/admin.blade.php (P360 Admin Shell · Full Width + Modal Mode) --}}
@php
  // ===== Título, tema y modo =====
  $pageTitle      = trim($__env->yieldContent('title')) ?: 'Panel Administrativo · Pactopia360';
  $bodyTheme      = session('ui.theme', 'light'); // 'light' | 'dark'
  $htmlThemeClass = $bodyTheme === 'dark' ? 'theme-dark' : 'theme-light';

  // MODO MODAL: si viene ?modal=1 en URL (iframe)
  $isModal = request()->boolean('modal');

  // ===== Layout width control =====
  // Por defecto Admin = FULL WIDTH (ocupa todo el espacio)
  // Si una vista quiere "contenedor", puede definir: @section('contentLayout','contained')
  $contentLayout = trim($__env->yieldContent('contentLayout')) ?: 'full'; // full | contained
  $isContained   = ($contentLayout === 'contained');

  // ===== Page class (CRÍTICO) =====
  // Permite que las vistas hagan: @section('pageClass','mi-clase')
  // y que el CSS por selector body.mi-clase funcione.
  $pageClass = trim($__env->yieldContent('pageClass'));

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
<html lang="es"
      class="h-100 {{ $htmlThemeClass }} {{ $isModal ? 'p360-is-modal' : '' }}"
      data-theme="{{ $bodyTheme }}"
      data-layout="{{ $isContained ? 'contained' : 'full' }}">
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

        /* Compat tokens (para parciales antiguos) */
        --ink: var(--text);
        --card: var(--card-bg);

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
        --header-h:56px;

        /* Control de ancho del contenido */
        --content-max: 100%;
        --footer-max: 100%;
        --modal-max: 1280px;

        --radius-sm:10px; --radius-md:12px; --radius-lg:14px;
        --shadow-1:0 2px 8px rgba(0,0,0,.06); --shadow-2:0 10px 28px rgba(0,0,0,.12);
        --safe-top:  env(safe-area-inset-top, 0px);
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

        /* Compat */
        --ink: var(--text);
        --card: var(--card-bg);
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
        position:fixed; inset:0 0 auto 0;
        height:var(--header-h);
        background:var(--topbar-bg); color:var(--topbar-fg);
        border-bottom:1px solid var(--topbar-border);
        z-index:1100; display:flex; align-items:stretch;
        padding-top: max(0px, var(--safe-top));
      }
      .p360-topbar > *{width:100%}

      /* App shell */
      .admin-app{
        flex:1 1 auto; min-height:0; display:flex; overflow:hidden;
        padding-top:var(--header-h);
      }

      .admin-content{
        flex:1 1 auto; min-height:0; overflow:auto; -webkit-overflow-scrolling:touch;
        background:var(--bg);
        display:flex; flex-direction:column;
      }

      /* Contenido */
      .page-container{
        padding: clamp(10px,2.0vw,18px);
        flex:1 1 auto;
        width:100%;
        min-width:0;
      }

      .page-shell{
        width:100%;
        max-width: var(--content-max);
        margin-inline: 0; /* full by default */
        min-width:0;
      }

      /* Si una vista pide contenedor */
      html[data-layout="contained"]{
        --content-max: 1280px;
        --footer-max: 1280px;
      }
      html[data-layout="contained"] .page-shell{
        margin-inline:auto;
      }

      /* ==========================================================================
         HARD FULL-WIDTH OVERRIDES (anti CSS externo)
         Si algún CSS global vuelve a meter max-width/margin auto, esto lo revienta.
         ========================================================================== */
      html[data-layout="full"]{
        --content-max: 100% !important;
        --footer-max: 100% !important;
      }
      html[data-layout="full"] body .admin-content .page-container{
        width:100% !important;
        max-width:none !important;
        margin:0 !important;
      }
      html[data-layout="full"] body .admin-content .page-shell{
        width:100% !important;
        max-width:none !important;
        margin-left:0 !important;
        margin-right:0 !important;
      }

      /* Footer */
      .admin-footer{
        margin-top:auto;
        border-top:1px solid var(--card-border);
        background:var(--bg);
        padding-bottom:var(--safe-bottom)
      }
      .admin-footer .footer-inner{
        max-width: var(--footer-max);
        margin-inline:auto;
        padding:14px 16px;
        color:var(--muted); font-size:12px;
        display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
      }
      .admin-footer .meta a{ color:inherit; text-decoration:underline; opacity:.9; margin-left:12px }
      [data-theme="dark"] .admin-footer{ border-top-color:rgba(255,255,255,.10) }

      /* Page header sticky (si la vista lo define) */
      #page-header{
        position:sticky;
        top:0;
        z-index:5;
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
      #p360-alerts{ position:fixed; right:16px; bottom:calc(16px + var(--safe-bottom)); display:flex; flex-direction:column; gap:8px; z-index:1400; max-width:min(92vw,520px) }
      .toast{ background:#111827; color:#fff; border-radius:12px; padding:10px 12px; box-shadow:var(--shadow-2); display:flex; align-items:center; gap:10px }
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
      html[data-density="compact"] .page-container{ padding: clamp(8px,1.6vw,14px) }

      /* Si algún widget dibuja legal en TOPBAR, no lo mostramos ahí */
      .p360-topbar .topbar-copy, .p360-topbar .copyright, .p360-topbar .legal, .p360-topbar [data-copyright]{ display:none!important }

      @media (max-width: 720px){
        .admin-footer .footer-inner{ padding:12px; gap:8px }
        .admin-footer .meta a{ margin-left:8px }
      }

      @media (prefers-reduced-motion: reduce){ *{transition:none!important;animation:none!important;scroll-behavior:auto!important} }
      @media (forced-colors: active){ .p360-topbar{ border-bottom:1px solid CanvasText } }
      @media print{ .p360-topbar, #nebula-sidebar, #p360-alerts, #p360-progress{ display:none !important } .admin-app{ padding-top:0 } }

      /* ==========================
         MODO MODAL (iframe)
         ========================== */
      html.p360-is-modal body{ overflow:auto !important; }
      html.p360-is-modal .admin-modal-page{ padding: 14px; }
      html.p360-is-modal .admin-modal-shell{
        max-width: var(--modal-max);
        margin-inline:auto;
      }
      html.p360-is-modal .admin-modal-shell > *:first-child{ margin-top: 0; }
    </style>

    {{-- Kill-switch CSS extra para el topbar --}}
    <style id="p360-legal-fix">
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

    {{-- IMPORTANTE: estilos de cada vista/módulo --}}
    @stack('styles')
  </head>

  {{-- FIX CRÍTICO: inyectar pageClass en el body para que funcione body.pageClass --}}
  <body class="d-flex flex-column min-vh-100 {{ $pageClass }} {{ $isModal ? 'p360-is-modal-body' : '' }}">
    @if(!$isModal)
      <a href="#p360-main" class="skiplink">Saltar al contenido</a>

      {{-- Header fijo --}}
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
          <div class="page-container">
            <div class="page-shell">
              @yield('content')
            </div>
          </div>

          {{-- Footer --}}
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

    @else
      {{-- MODO MODAL (iframe) --}}
      <main class="admin-modal-page" role="main" aria-live="polite">
        @if (session('status'))
          <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
          </div>
        @endif

        <div class="admin-modal-shell">
          @yield('content')
        </div>
      </main>
    @endif

    {{-- ===== JS utilitario global ===== --}}
    <script>
    (function(){
      'use strict';

      const root     = document.documentElement;
      const isModal  = root.classList.contains('p360-is-modal');
      if (isModal) return;

      const main     = document.getElementById('p360-main');
      const progress = document.getElementById('p360-progress');
      const loading  = document.getElementById('p360-loading');
      const alerts   = document.getElementById('p360-alerts');
      const cmd      = document.getElementById('p360-cmd');
      const cmdIn    = document.getElementById('p360-cmd-input');

      window.p360 = window.p360 || {};
      window.p360.once = function(flag, cb){
        const k = '__p360_'+flag;
        if (window[k]) return false;
        window[k] = 1;
        try{ cb && cb(); }catch(e){ console.error(e); }
        return true;
      };

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
          const el = document.querySelector('#globalSearch,#global-search, form[role="search"] input[type="search"], input[type="search"][name="q"], form[role="search"] input[name="q"]');
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

      function syncHeaderHeight(){
        const tb = document.getElementById('p360-topbar') || document.getElementById('topbar');
        if(!tb) return;
        const h = Math.max(48, Math.round(tb.getBoundingClientRect().height));
        root.style.setProperty('--header-h', h + 'px');
      }
      addEventListener('load', ()=>requestAnimationFrame(syncHeaderHeight), {once:true});
      addEventListener('resize', ()=>requestAnimationFrame(syncHeaderHeight));
      setTimeout(syncHeaderHeight, 160);

      try{
        const th = localStorage.getItem('p360.theme');   if(th) P360.setTheme(th);
        const dn = localStorage.getItem('p360.density'); if(dn) P360.setDensity(dn);
      }catch(_){}

      addEventListener('keydown', (e)=>{
        const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;
        if (ctrl && e.key.toLowerCase()==='k'){ e.preventDefault(); P360.focusSearch(); }
        if (e.key==='/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)){ e.preventDefault(); P360.focusSearch(); }
        if (ctrl && e.key.toLowerCase()==='p'){ e.preventDefault(); P360.openCmd(); }
        if (e.key==='Escape' && cmd && cmd.style.display==='grid'){ e.preventDefault(); P360.closeCmd(); }
      });
      cmd && cmd.addEventListener('click', (ev)=>{ if(ev.target===cmd) P360.closeCmd(); });

      main && main.addEventListener('scroll', ()=>{
        const ph = document.getElementById('page-header');
        if(!ph) return; ph.classList.toggle('affix-shadow', main.scrollTop > 6);
      }, {passive:true});

      const keyScroll = ()=> 'p360.scroll.' + (location.pathname || '');
      addEventListener('beforeunload', ()=>{ try{ main && sessionStorage.setItem(keyScroll(), String(main.scrollTop||0)); }catch(_){ }});
      addEventListener('load', ()=>{ try{ const y = parseInt(sessionStorage.getItem(keyScroll())||'0',10)||0; main && main.scrollTo({top:y}); }catch(_){ }});

      (function perf(){ try{
        const t = performance.timing; const tt = Math.max(0, t.domComplete - t.navigationStart);
        if(tt) console.log('%cP360','background:#111;color:#fff;padding:2px 6px;border-radius:6px','domComplete='+tt+'ms');
      }catch(_){}})();

    })();
    </script>

    {{-- scripts de cada vista/módulo --}}
    @stack('scripts')
    @yield('scripts')

    <noscript>
      <div style="position:fixed;left:0;right:0;top:0;background:#b91c1c;color:#fff;padding:.5rem 1rem;z-index:99999;text-align:center">
        Para usar el panel correctamente, habilita JavaScript.
      </div>
    </noscript>

    @if(!$isModal)
      <style>
        .novabot-floating, #novabot-widget, .chat-bubble, [data-novabot]{
          position:fixed !important; right:16px !important; bottom:calc(84px + var(--safe-bottom)) !important; z-index:1200 !important;
        }
      </style>
    @endif
  </body>
</html>
