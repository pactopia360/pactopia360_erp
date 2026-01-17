{{-- C:\wamp64\www\pactopia360_erp\resources\views\layouts\admin.blade.php --}}
{{-- P360 Admin Layout · FULL-BLEED header · Nebula sidebar · v3.0 (Local) --}}

@php
  $pageTitle      = trim($__env->yieldContent('title')) ?: 'Panel Administrativo · Pactopia360';
  $bodyTheme      = session('ui.theme', 'light'); // light|dark
  $htmlThemeClass = $bodyTheme === 'dark' ? 'theme-dark' : 'theme-light';

  $isModal = request()->boolean('modal');

  $contentLayout = trim($__env->yieldContent('contentLayout')) ?: 'full'; // full|contained
  $isContained   = ($contentLayout === 'contained');

  $pageClass = trim($__env->yieldContent('pageClass'));

  // CSS externos (opcionales)
  $minSize   = 16;
  $BASE_ABS  = public_path('assets/admin/css/base.css');
  $UI_ABS    = public_path('assets/admin/css/ui.css');
  $APP_ABS   = public_path('assets/admin/css/app.css');
  $FRAME_ABS = public_path('assets/admin/css/frame.css');

  $BASE_URL  = (is_file($BASE_ABS)  && filesize($BASE_ABS)  > $minSize) ? asset('assets/admin/css/base.css')  .'?v='.filemtime($BASE_ABS)  : null;
  $UI_URL    = (is_file($UI_ABS)    && filesize($UI_ABS)    > $minSize) ? asset('assets/admin/css/ui.css')    .'?v='.filemtime($UI_ABS)    : null;
  $APP_URL   = (is_file($APP_ABS)   && filesize($APP_ABS)   > $minSize) ? asset('assets/admin/css/app.css')   .'?v='.filemtime($APP_ABS)   : null;
  $FRAME_URL = (is_file($FRAME_ABS) && filesize($FRAME_ABS) > $minSize) ? asset('assets/admin/css/frame.css') .'?v='.filemtime($FRAME_ABS) : null;

  $SKIN_URL  = $BASE_URL ?: ($UI_URL ?: $APP_URL);
@endphp

<!doctype html>
<html lang="es"
      class="{{ $htmlThemeClass }} {{ $isModal ? 'p360-is-modal' : '' }}"
      data-theme="{{ $bodyTheme }}"
      data-layout="{{ $isContained ? 'contained' : 'full' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light dark">
  <title>{{ $pageTitle }}</title>

  {{-- =========================
       CRITICAL: TOKENS + LAYOUT
       ========================= --}}
  <style id="p360-critical">
    :root{
      /* ===== Brand ===== */
      --brand-red:#e11d48;
      --accent:#0e2a3b;

      /* ===== Colors ===== */
      --bg:#f6f7f9;
      --text:#0f172a;
      --muted:#64748b;

      --card-bg:#ffffff;
      --card-border:rgba(0,0,0,.08);
      --panel-bg:#f8fafc;

      /* compat */
      --ink: var(--text);
      --card: var(--card-bg);
      --bd: var(--card-border);

      /* ===== Layout ===== */
      --header-h:56px;

      /* Sidebar widths (source of truth) */
      --sidebar-w:260px;
      --sidebar-w-collapsed:72px;

      /* offset efectivo para el main */
      --sidebar-offset: var(--sidebar-w);

      /* Contained mode */
      --content-max: 100%;
      --footer-max: 100%;
      --modal-max: 1280px;

      /* UI */
      --shadow-1: 0 2px 8px rgba(0,0,0,.08);
      --shadow-2: 0 12px 32px rgba(0,0,0,.18);

      --safe-top: env(safe-area-inset-top, 0px);
      --safe-bottom: env(safe-area-inset-bottom, 0px);
    }

    html[data-theme="dark"], html.theme-dark{
      --bg:#0b1220;
      --text:#e5e7eb;
      --muted:#94a3b8;

      --card-bg:#0f172a;
      --card-border:rgba(255,255,255,.10);
      --panel-bg:#0c172b;

      --ink: var(--text);
      --card: var(--card-bg);
      --bd: var(--card-border);

      --accent:#7ba1ff;
    }

    /* Flags colapso (Nebula usa html.sidebar-collapsed) */
    html.sidebar-collapsed,
    html[data-sidebar="collapsed"]{
      --sidebar-offset: var(--sidebar-w-collapsed);
    }

    /* Reset base */
    html,body{height:100%}
    *,*::before,*::after{ box-sizing:border-box }
    body{
      margin:0;
      min-height:100vh;
      display:flex;
      flex-direction:column;
      overflow:hidden;
      background:var(--bg);
      color:var(--text);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
    }

    /* =========================
       TOPBAR WRAPPER (FULL BLEED)
       ========================= */
    #p360-topbar{
      position:fixed;
      top:0; left:0; right:0;
      height:var(--header-h);
      z-index:1200;
      background: var(--card-bg);
      border-bottom:1px solid var(--card-border);
      padding-top: max(0px, var(--safe-top));
    }

    /* fuerza que el header interno NO tenga márgenes raros */
    #p360-topbar > *{
      width:100% !important;
      max-width:none !important;
      margin:0 !important;
      border-radius:0 !important;
    }

    /* =========================
       APP SHELL (SIDEBAR + MAIN)
       ========================= */
    .admin-app{
      position:relative;
      flex:1 1 auto;
      min-height:0;
      overflow:hidden;
      padding-top: var(--header-h);
    }

    /* Sidebar anchor (solo posición/medidas; el look vive en el partial) */
    #nebula-sidebar{
      position:fixed !important;
      top: var(--header-h) !important;
      left: 0 !important;
      width: var(--sidebar-offset) !important;
      height: calc(100dvh - var(--header-h)) !important;
      margin:0 !important;
      border-radius:0 !important;
      z-index:1100 !important;
    }

    /* MAIN */
    .admin-content{
      position:relative;
      height: calc(100dvh - var(--header-h));
      overflow:auto;
      -webkit-overflow-scrolling:touch;
      display:flex;
      flex-direction:column;
      min-width:0;

      /* desktop: se recorre por el sidebar */
      margin-left: var(--sidebar-offset);
      width: calc(100% - var(--sidebar-offset));
    }

    /* Page wrapper */
    .page-container{
      width:100%;
      min-width:0;
      padding: clamp(12px, 2vw, 18px);
      flex:1 1 auto;
    }
    .page-shell{
      width:100%;
      min-width:0;
      max-width: var(--content-max);
      margin: 0;
    }

    /* Contained opt-in */
    html[data-layout="contained"]{
      --content-max: 1280px;
      --footer-max: 1280px;
    }
    html[data-layout="contained"] .page-shell{
      margin-inline:auto;
    }

    /* Footer */
    .admin-footer{
      margin-top:auto;
      border-top:1px solid var(--card-border);
      background:var(--bg);
      padding-bottom: var(--safe-bottom);
    }
    .admin-footer .footer-inner{
      max-width: var(--footer-max);
      margin-inline:auto;
      padding: 14px 16px;
      color: var(--muted);
      font-size: 12px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }
    .admin-footer .meta a{
      color:inherit;
      text-decoration:underline;
      opacity:.9;
      margin-left:12px;
    }

    /* Sticky page header (optional) */
    #page-header{
      position:sticky;
      top:0;
      z-index:10;
      background: color-mix(in oklab, var(--card-bg) 92%, transparent);
      backdrop-filter:saturate(140%) blur(8px);
      border-bottom:1px solid var(--card-border);
    }
    .affix-shadow{ box-shadow: 0 10px 28px rgba(0,0,0,.10); }

    /* Skip link */
    .skiplink{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
    .skiplink:focus{
      left:12px; top:12px; width:auto; height:auto;
      padding:8px 10px; border-radius:10px;
      background:#111827; color:#fff; z-index:2000;
      box-shadow: var(--shadow-2);
    }

    /* Widgets */
    #p360-progress{ position:fixed; left:0; top:0; height:3px; width:0%; z-index:2000; background:linear-gradient(90deg,var(--brand-red),var(--accent)); transition:width .2s ease, opacity .3s ease; }
    #p360-loading{ position:fixed; inset:0; display:none; place-items:center; background:rgba(0,0,0,.25); z-index:1900; backdrop-filter: blur(2px) }
    #p360-loading .spinner{ width:56px; height:56px; border-radius:50%; border:6px solid #fff; border-top-color:transparent; animation:spin 1s linear infinite }
    @keyframes spin{ to{ transform:rotate(360deg) } }
    #p360-alerts{ position:fixed; right:16px; bottom:calc(16px + var(--safe-bottom)); display:flex; flex-direction:column; gap:8px; z-index:2100; max-width:min(92vw,520px) }
    .toast{ background:#111827; color:#fff; border-radius:12px; padding:10px 12px; box-shadow:var(--shadow-2); display:flex; align-items:center; gap:10px }
    .toast .x{ background:transparent;border:0;color:#fff;cursor:pointer;font-weight:800 }

    #p360-cmd{ position:fixed; inset:0; display:none; place-items:center; z-index:2200; background:rgba(0,0,0,.35) }
    #p360-cmd .cmd-card{ width:min(720px, 92vw); background:var(--card-bg); color:var(--text); border:1px solid var(--card-border); border-radius:14px; box-shadow:var(--shadow-2); padding:12px }
    #p360-cmd input{ width:100%; border:1px solid var(--card-border); border-radius:10px; padding:10px 12px; outline:0; background:var(--panel-bg); color:var(--text) }

    /* MOBILE: sidebar overlay -> main full width */
    @media (max-width: 1099.98px){
      .admin-content{
        margin-left: 0 !important;
        width: 100% !important;
      }
      #nebula-sidebar{
        width: min(86vw, var(--sidebar-w)) !important;
        max-width: 360px !important;
      }
    }

    @media (prefers-reduced-motion: reduce){
      *{ transition:none!important; animation:none!important; scroll-behavior:auto!important; }
    }

    @media print{
      #p360-topbar, #nebula-sidebar, #p360-alerts, #p360-progress{ display:none !important; }
      .admin-app{ padding-top:0 !important; }
      .admin-content{ margin-left:0 !important; width:100% !important; height:auto !important; overflow:visible !important; }
    }

    /* MODAL MODE */
    html.p360-is-modal body{ overflow:auto !important; }
    html.p360-is-modal .admin-modal-page{ padding:14px; }
    html.p360-is-modal .admin-modal-shell{ max-width: var(--modal-max); margin-inline:auto; }
  </style>

  {{-- Topbar kill-switch (lo conservas) --}}
  <style id="p360-legal-fix">
    #p360-topbar small,
    #p360-topbar .copyright,
    #p360-topbar .legal,
    #p360-topbar [data-copyright],
    #p360-topbar #p360-copy{ display:none!important; }
  </style>

  {{-- CSS externos (opcionales) --}}
  @if($SKIN_URL)  <link rel="stylesheet" href="{{ $SKIN_URL }}"> @endif
  @if($FRAME_URL) <link rel="stylesheet" href="{{ $FRAME_URL }}"> @endif

  @stack('styles')
</head>

<body class="{{ $pageClass }} {{ $isModal ? 'p360-is-modal-body' : '' }}">
@if(!$isModal)
  <a href="#p360-main" class="skiplink">Saltar al contenido</a>

  {{-- Header fijo (se conserva, solo lo envuelve el layout) --}}
  <header id="p360-topbar" role="banner" aria-label="Barra superior">
    @includeIf('layouts.partials.header')
  </header>

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

      <div class="page-container">
        <div class="page-shell">
          @yield('content')
        </div>
      </div>

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

  {{-- Widgets --}}
  <div id="p360-progress" aria-hidden="true"></div>
  <div id="p360-loading" role="status" aria-live="polite" aria-label="Cargando"><div class="spinner"></div></div>
  <div id="p360-alerts" aria-live="polite"></div>
  <div id="p360-cmd"><div class="cmd-card"><input type="search" id="p360-cmd-input" placeholder="Escribe para buscar…  (Esc para cerrar)"></div></div>

@else
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

{{-- CSRF for fetch --}}
<script>
(function(){
  'use strict';
  const meta = document.querySelector('meta[name="csrf-token"]');
  const token = meta ? meta.getAttribute('content') : '';

  if (token && typeof window.fetch === 'function' && !window.__p360_fetch_csrf__) {
    window.__p360_fetch_csrf__ = 1;
    const _fetch = window.fetch.bind(window);

    window.fetch = function(input, init){
      init = init || {};
      const method = String(init.method || 'GET').toUpperCase();
      const headers = new Headers(init.headers || {});
      headers.set('X-Requested-With', 'XMLHttpRequest');

      if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
        if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', token);
        if (!headers.has('X-XSRF-TOKEN')) headers.set('X-XSRF-TOKEN', token);
      }

      init.headers = headers;
      if (!init.credentials) init.credentials = 'same-origin';
      return _fetch(input, init);
    };
  }
})();
</script>

{{-- Core utils --}}
<script>
(function(){
  'use strict';

  const root = document.documentElement;
  if (root.classList.contains('p360-is-modal')) return;

  const main     = document.getElementById('p360-main');
  const progress = document.getElementById('p360-progress');
  const loading  = document.getElementById('p360-loading');
  const alerts   = document.getElementById('p360-alerts');
  const cmd      = document.getElementById('p360-cmd');
  const cmdIn    = document.getElementById('p360-cmd-input');

  window.P360 = window.P360 || {};

  window.P360.setTheme = function(t){
    if(!t) return;
    root.dataset.theme = t;
    const dark = (t === 'dark');
    root.classList.toggle('theme-dark', dark);
    root.classList.toggle('theme-light', !dark);
    try{ localStorage.setItem('p360.theme', t); }catch(_){}
  };

  window.P360.toggleTheme = function(){
    window.P360.setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
  };

  window.P360.toast = function(msg, opts){
    opts = opts || {};
    if(!alerts) return;
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = '<div style="flex:1">'+(msg||'')+'</div><button class="x" aria-label="Cerrar">×</button>';
    alerts.appendChild(el);
    const close = ()=>{ el.style.opacity='0'; setTimeout(()=>el.remove(), 220); };
    el.querySelector('.x').onclick = close;
    setTimeout(close, opts.timeout || 4200);
  };

  window.P360.loading = {
    show(){ if(loading) loading.style.display='grid'; },
    hide(){ if(loading) loading.style.display='none'; }
  };

  window.P360.progress = {
    start(){
      if(!progress) return;
      progress.style.opacity='1';
      progress.style.width='25%';
      (function tick(){
        if(progress.style.opacity!=='1') return;
        const w = parseFloat(progress.style.width) || 0;
        progress.style.width = Math.min(w + Math.random()*18, 90) + '%';
        setTimeout(tick, 180);
      })();
    },
    done(){
      if(!progress) return;
      progress.style.width='100%';
      setTimeout(()=>{ progress.style.opacity='0'; progress.style.width='0%'; }, 250);
    }
  };

  window.P360.focusSearch = function(){
    const el = document.querySelector('#globalSearch, form[role="search"] input[type="search"], input[type="search"][name="q"]');
    if(el){ el.focus(); el.select && el.select(); }
  };

  window.P360.openCmd = function(){
    if(!cmd) return;
    cmd.style.display='grid';
    if(cmdIn){ cmdIn.value=''; setTimeout(()=>cmdIn.focus(), 10); }
  };

  window.P360.closeCmd = function(){
    if(!cmd) return;
    cmd.style.display='none';
  };

  function syncHeaderHeight(){
    const tb = document.getElementById('p360-topbar');
    if(!tb) return;
    const h = Math.max(48, Math.round(tb.getBoundingClientRect().height));
    root.style.setProperty('--header-h', h + 'px');
  }
  addEventListener('load', ()=>requestAnimationFrame(syncHeaderHeight), {once:true});
  addEventListener('resize', ()=>requestAnimationFrame(syncHeaderHeight));
  setTimeout(syncHeaderHeight, 120);

  try{
    const th = localStorage.getItem('p360.theme');
    if(th) window.P360.setTheme(th);
  }catch(_){}

  addEventListener('keydown', (e)=>{
    const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;
    if(ctrl && e.key.toLowerCase()==='k'){ e.preventDefault(); window.P360.focusSearch(); }
    if(e.key==='/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)){ e.preventDefault(); window.P360.focusSearch(); }
    if(ctrl && e.key.toLowerCase()==='p'){ e.preventDefault(); window.P360.openCmd(); }
    if(e.key==='Escape' && cmd && cmd.style.display==='grid'){ e.preventDefault(); window.P360.closeCmd(); }
  });

  cmd && cmd.addEventListener('click', (ev)=>{ if(ev.target===cmd) window.P360.closeCmd(); });

  main && main.addEventListener('scroll', ()=>{
    const ph = document.getElementById('page-header');
    if(!ph) return;
    ph.classList.toggle('affix-shadow', main.scrollTop > 6);
  }, {passive:true});

})();
</script>

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
      position:fixed !important;
      right:16px !important;
      bottom:calc(84px + var(--safe-bottom)) !important;
      z-index:1600 !important;
    }
  </style>
@endif

</body>
</html>
