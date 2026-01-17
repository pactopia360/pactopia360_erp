{{-- C:\wamp64\www\pactopia360_erp\resources\views\layouts\partials\header.blade.php --}}
{{-- P360 Admin Topbar v3.9 · FULL WIDTH · responsive grid · mobile sidebar toggle · no header-h override --}}

@php
  use Illuminate\Support\Facades\Route;

  $user = auth('admin')->user();
  $userName   = $user?->name ?? $user?->nombre ?? 'Admin';
  $userEmail  = $user?->email ?? '';
  $brandUrl   = Route::has('admin.home') ? route('admin.home') : url('/');

  $logoLight  = asset('assets/admin/img/logo-pactopia360-dark.png');
  $logoDark   = asset('assets/admin/img/logo-pactopia360-white.png');

  $urlPerfil   = Route::has('admin.perfil') ? route('admin.perfil')
                : (Route::has('admin.profile') ? route('admin.profile') : '#');
  $urlConfig   = Route::has('admin.config.index') ? route('admin.config.index')
                : (Route::has('admin.configuracion.index') ? route('admin.configuracion.index') : '#');
  $logoutRoute = Route::has('admin.logout') ? route('admin.logout')
                : (Route::has('logout') ? route('logout') : '#');

  $searchUrl     = Route::has('admin.search') ? route('admin.search') : '#';
  $notifCountUrl = Route::has('admin.notificaciones.count') ? route('admin.notificaciones.count') : null;
  $notifListUrl  = Route::has('admin.notificaciones.list')  ? route('admin.notificaciones.list')
                  : (Route::has('admin.notificaciones') ? route('admin.notificaciones') : null);
  $heartbeatUrl  = Route::has('admin.ui.heartbeat') ? route('admin.ui.heartbeat') : null;

  // Carrito SAT (lado cliente). Ajusta si tu ruta difiere.
  $cartUrl = Route::has('cliente.carrito.index')
      ? route('cliente.carrito.index')
      : (Route::has('cliente.cart.index') ? route('cliente.cart.index') : '#');

  $satUrl = Route::has('cliente.sat.index')
      ? route('cliente.sat.index')
      : (Route::has('cliente.sat') ? route('cliente.sat') : '#');

  $envName = app()->environment();
  $unread  = (int) (session('admin_unread_notifications', 0));
@endphp

<header id="topbar" class="p360-header" role="banner"
        data-search-url="{{ $searchUrl }}"
        @if($notifCountUrl) data-notif-count-url="{{ $notifCountUrl }}" @endif
        @if($notifListUrl)  data-notif-list-url="{{ $notifListUrl }}"   @endif
        @if($heartbeatUrl)  data-heartbeat-url="{{ $heartbeatUrl }}"    @endif
        data-env="{{ $envName }}">

  {{-- GRID FULL WIDTH (3 zonas) --}}
  <div class="p360-h-grid">

    {{-- LEFT --}}
    <div class="p360-h-left">
      {{-- ✅ Toggle móvil sidebar (overlay) --}}
      <button id="btnSidebar" class="p360-btn p360-btn-ghost p360-hamb" type="button"
              aria-label="Abrir menú" title="Menú" aria-expanded="false">
        <span aria-hidden="true">☰</span>
      </button>

      <a class="p360-brand" href="{{ $brandUrl }}" aria-label="Inicio">
        <img class="p360-logo p360-logo-light" src="{{ $logoLight }}" alt="Pactopia360" width="160" height="32" decoding="async" fetchpriority="high">
        <img class="p360-logo p360-logo-dark"  src="{{ $logoDark  }}"  alt="Pactopia360" width="160" height="32" decoding="async">
      </a>

      <span class="p360-env" title="Entorno de ejecución">
        <span class="p360-env-text">{{ strtoupper($envName) }}</span>
        <span id="hbDot" class="p360-hb-dot" aria-label="Estado del servidor" title="Estado"></span>
      </span>
    </div>

    {{-- CENTER --}}
    <div class="p360-h-center">
      <form class="p360-search" role="search" method="get"
            action="{{ $searchUrl }}"
            @if($searchUrl !== '#') data-pjax-form @endif
            onsubmit="return this.q.value.trim().length>0">
        <span class="p360-search-ico" aria-hidden="true">🔎</span>
        <input id="globalSearch" name="q" type="search"
               placeholder="Buscar en el panel… (Ctrl + K o /)"
               autocomplete="off" aria-label="Buscar en el panel" enterkeyhint="search">
        <kbd class="p360-kbd" aria-hidden="true">Ctrl + K</kbd>
      </form>
    </div>

    {{-- RIGHT --}}
    <div class="p360-h-right">

      <details class="p360-dd" data-menu="quick">
        <summary class="p360-btn p360-btn-ghost" title="Acciones rápidas" aria-label="Acciones rápidas">⚡</summary>
        <div class="p360-ddp" style="min-width:260px">
          <div class="p360-ddh">Acciones rápidas</div>
          <div class="p360-ddb">
            <nav class="p360-menu">
              @if(Route::has('admin.pagos.create'))    <a href="{{ route('admin.pagos.create') }}">Nuevo pago</a> @endif
              @if(Route::has('admin.clientes.create')) <a href="{{ route('admin.clientes.create') }}">Nuevo cliente</a> @endif
              @if(Route::has('admin.reportes.index'))  <a href="{{ route('admin.reportes.index') }}">Reportes</a> @endif
              @if(Route::has('admin.home'))            <a href="{{ route('admin.home') }}">Home</a> @endif
              @if(Route::has('admin.dashboard'))       <a href="{{ route('admin.dashboard') }}">Dashboard</a> @endif
            </nav>
          </div>
        </div>
      </details>

      <button id="btnTheme" class="p360-btn p360-btn-ghost" type="button"
              aria-label="Cambiar tema" title="Cambiar tema" aria-pressed="false">
        <span class="p360-ico" aria-hidden="true">🌓</span>
        <span id="themeLabel" class="p360-theme-label" aria-live="polite">Modo claro</span>
      </button>

      <details class="p360-dd" data-menu="notifications">
        <summary class="p360-btn p360-btn-ghost" title="Notificaciones" aria-label="Notificaciones">
          🔔
          <span class="p360-badge" id="notifBadge" aria-label="{{ $unread }} sin leer" @if($unread<=0) hidden @endif>{{ $unread>99?'99+':$unread }}</span>
        </summary>
        <div class="p360-ddp" style="min-width:320px">
          <div class="p360-ddh">Notificaciones</div>
          <div class="p360-ddb" id="p360NotifBody" data-state="idle">
            @if($unread>0)
              <p style="margin:0 0 10px">Tienes {{ $unread }} notificaciones pendientes.</p>
              @if(Route::has('admin.notificaciones'))
                <a class="p360-btn p360-btn-solid" href="{{ route('admin.notificaciones') }}">Ver todas</a>
              @endif
            @else
              <p class="p360-muted" style="margin:0">Sin notificaciones.</p>
            @endif
          </div>
        </div>
      </details>

      {{-- CARRITO SAT --}}
      <div id="satCartHeader" class="p360-sat" style="display:none;">
        <button type="button"
                class="p360-btn p360-btn-ghost p360-cart-btn"
                title="Carrito de descargas SAT"
                aria-label="Carrito de descargas SAT"
                onclick="window.location.href='{{ $satUrl !== '#' ? $satUrl : $cartUrl }}#block-downloads'">
          <span aria-hidden="true">🛒</span>
          <span class="p360-cart-pill">
            <span id="satCartHeaderCount">0</span> ·
            <span id="satCartHeaderTotal">$0.00</span>
          </span>
        </button>

        <button type="button"
                class="p360-sat-pay is-disabled"
                id="satCartHeaderPay"
                disabled
                onclick="window.location.href='{{ $satUrl !== '#' ? $satUrl : $cartUrl }}#block-downloads'">
          Proceder a pago
        </button>
      </div>

      <button id="btnNovaBot" class="p360-btn p360-btn-ghost" type="button" title="Abrir asistente" aria-label="Abrir asistente">
        🤖 <span class="p360-sr">Asistente</span>
      </button>

      <details class="p360-dd" data-menu="profile">
        <summary class="p360-btn p360-btn-ghost p360-who" aria-label="Menú de usuario">
          <img class="p360-avatar" src="{{ $user?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($userName).'&background=0D8ABC&color=fff' }}" alt="">
          <span class="p360-who-txt">
            <strong class="p360-who-name">{{ $userName }}</strong>
            <small class="p360-muted">Panel Administrativo</small>
          </span>
          <span aria-hidden="true">▾</span>
        </summary>

        <div class="p360-ddp">
          <div class="p360-ddh">{{ $userName }}</div>
          <div class="p360-ddb">
            <div class="p360-muted" style="font-size:12px;margin:0 0 10px;word-break:break-word">{{ $userEmail }}</div>

            <nav class="p360-menu">
              @if($urlPerfil !== '#') <a href="{{ $urlPerfil }}">Mi perfil</a> @endif
              @if($urlConfig !== '#') <a href="{{ $urlConfig }}">Configuración</a> @endif
              <hr class="p360-hr">
              @if($logoutRoute !== '#')
                <form method="post" action="{{ $logoutRoute }}" id="logoutForm">@csrf
                  <button class="p360-btn p360-btn-danger w-100" type="submit" id="logoutBtn">Cerrar sesión</button>
                </form>
              @endif
            </nav>
          </div>
        </div>
      </details>

    </div>
  </div>
</header>

<style>
  /* IMPORTANTE: NO redefinir --header-h aquí. Lo sincroniza admin.blade.php (syncHeaderHeight). */
  .p360-header{
    width:100% !important;
    max-width:none !important;
    margin:0 !important;
    position:relative; /* el wrapper fixed está en admin.blade.php */
    z-index:1;

    min-height:56px;
    background:var(--topbar-bg, #fff);
    color:var(--topbar-fg, inherit);
    border-bottom:1px solid var(--topbar-border, rgba(0,0,0,.08));
    backdrop-filter:saturate(180%) blur(6px);
  }
  html.theme-dark .p360-header{ background:var(--topbar-bg, #0b1220); }

  .p360-header, .p360-header *{ box-sizing:border-box; }
  .p360-sr{ position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden; }

  /* 3 zonas: left / center / right (full width real) */
  .p360-h-grid{
    width:100%;
    display:grid;
    grid-template-columns: auto 1fr auto;
    align-items:center;
    gap:12px;
    padding: calc(6px + var(--safe-top, 0px)) 12px 8px;
    min-width:0;
  }

  .p360-h-left, .p360-h-center, .p360-h-right{ min-width:0; display:flex; align-items:center; gap:10px; }
  .p360-h-left{ justify-content:flex-start; }
  .p360-h-center{ justify-content:center; }
  .p360-h-right{ justify-content:flex-end; flex-wrap:nowrap; }

  /* Brand */
  .p360-brand{ display:inline-flex; align-items:center; gap:10px; text-decoration:none; min-width:0; }
  .p360-logo{ height:32px; max-width:160px; width:auto; }
  .p360-logo-dark{ display:none; }
  html.theme-dark .p360-logo-dark{ display:inline; }
  html.theme-dark .p360-logo-light{ display:none; }

  /* Buttons */
  .p360-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    height:38px;
    padding:0 10px;
    border-radius:10px;
    border:1px solid rgba(0,0,0,.08);
    background:transparent;
    color:inherit;
    cursor:pointer;
    user-select:none;
    white-space:nowrap;
  }
  html.theme-dark .p360-btn{ border-color:rgba(255,255,255,.12); }

  .p360-btn-ghost:hover{ background:rgba(0,0,0,.05); }
  html.theme-dark .p360-btn-ghost:hover{ background:rgba(255,255,255,.08); }

  .p360-btn-solid{
    display:inline-flex; justify-content:center; align-items:center;
    height:34px; padding:0 12px; border-radius:999px;
    border:1px solid rgba(0,0,0,.08);
    background:rgba(0,0,0,.06);
    text-decoration:none; color:inherit; font-weight:700; font-size:12px;
  }
  html.theme-dark .p360-btn-solid{ border-color:rgba(255,255,255,.12); background:rgba(255,255,255,.08); }

  .p360-btn-danger{
    width:100%;
    height:36px;
    border-radius:12px;
    border:1px solid rgba(185,28,28,.35);
    background:rgba(239,68,68,.08);
    color:#b91c1c;
    font-weight:800;
  }
  html.theme-dark .p360-btn-danger{
    color:#fecaca;
    border-color:rgba(248,113,113,.35);
    background:rgba(239,68,68,.14);
  }

  /* Hamburger: visible solo en móvil (overlay sidebar) */
  .p360-hamb{ display:none; width:40px; padding:0; font-size:18px; font-weight:900; }

  /* Env */
  .p360-env{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font:700 11px/1 system-ui;
    padding:4px 8px;
    border-radius:9999px;
    background:rgba(0,0,0,.06);
  }
  html.theme-dark .p360-env{ background:rgba(255,255,255,.08); }
  .p360-env-text{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:12ch; }
  .p360-hb-dot{
    width:8px;height:8px;border-radius:9999px;display:inline-block;background:#9ca3af;
    box-shadow:0 0 0 0 rgba(16,185,129,.0);
  }
  .p360-hb-dot.ok{background:#10b981;box-shadow:0 0 0 6px rgba(16,185,129,.18)}
  .p360-hb-dot.warn{background:#f59e0b;box-shadow:0 0 0 6px rgba(245,158,11,.18)}
  .p360-hb-dot.fail{background:#ef4444;box-shadow:0 0 0 6px rgba(239,68,68,.18)}

  /* Search (center) — ocupa TODO lo que pueda */
  .p360-search{
    display:flex;
    align-items:center;
    gap:8px;
    width: min(920px, 100%);
    max-width: 100%;
    padding:6px 10px;
    border-radius:12px;
    background:rgba(0,0,0,.04);
    border:1px solid rgba(0,0,0,.08);
    min-height:38px;
    min-width:0;
  }
  html.theme-dark .p360-search{ background:rgba(255,255,255,.06); border-color:rgba(255,255,255,.12); }
  .p360-search input{ flex:1; border:0; outline:0; background:transparent; min-width:0; color:inherit; }
  .p360-kbd{ font:700 10px/1 system-ui; background:rgba(0,0,0,.08); padding:2px 6px; border-radius:6px; }
  html.theme-dark .p360-kbd{ background:rgba(255,255,255,.12); }
  .p360-search-ico{ opacity:.85; }

  /* Badge */
  .p360-badge{
    display:inline-flex; min-width:18px; height:18px; padding:0 5px;
    border-radius:9px; background:#ef4444; color:#fff;
    font:800 10px/18px system-ui;
    margin-left:6px;
  }

  /* Dropdowns */
  .p360-dd{ position:relative; }
  .p360-dd > summary{ list-style:none; }
  .p360-dd > summary::-webkit-details-marker{ display:none; }

  .p360-ddp{
    position:absolute;
    right:0;
    top:calc(100% + 8px);
    background: var(--topbar-bg, #fff);
    color: inherit;
    border:1px solid rgba(0,0,0,.12);
    border-radius:12px;
    box-shadow:0 12px 30px rgba(0,0,0,.12);
    padding:8px;
    min-width:220px;
    z-index:200;
  }
  html.theme-dark .p360-ddp{ border-color:rgba(255,255,255,.12); box-shadow:0 12px 30px rgba(0,0,0,.35); }

  .p360-ddh{
    font:900 11px/1 system-ui;
    margin:6px 6px 8px;
    color:rgba(100,116,139,.95);
    text-transform:uppercase;
    letter-spacing:.10em;
  }
  html.theme-dark .p360-ddh{ color:rgba(156,163,175,.95); }

  .p360-ddb{ padding:0 6px 6px; }
  .p360-menu a{
    display:block;
    padding:8px 8px;
    border-radius:10px;
    text-decoration:none;
    color:inherit;
    font-weight:650;
  }
  .p360-menu a:hover{ background:rgba(0,0,0,.06); }
  html.theme-dark .p360-menu a:hover{ background:rgba(255,255,255,.08); }

  .p360-hr{ border:0; height:1px; background:rgba(0,0,0,.10); margin:8px 0; }
  html.theme-dark .p360-hr{ background:rgba(255,255,255,.12); }

  /* Profile */
  .p360-who{ gap:10px; }
  .p360-avatar{ width:24px;height:24px;border-radius:9999px;object-fit:cover; }
  .p360-who-txt{ display:flex; flex-direction:column; line-height:1; min-width:0; }
  .p360-who-name{ font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; }
  .p360-muted{ color:rgba(107,114,128,.95); }
  html.theme-dark .p360-muted{ color:rgba(156,163,175,.95); }

  /* SAT cart */
  .p360-sat{ display:flex; align-items:center; gap:8px; }
  .p360-cart-btn{ white-space:nowrap; }
  .p360-cart-pill{ font-size:11px; display:inline-flex; align-items:center; gap:4px; }

  .p360-sat-pay{
    font-size:11px;
    padding:0 12px;
    height:32px;
    border-radius:9999px;
    border:none;
    background:#22c55e;
    color:#fff;
    font-weight:800;
    cursor:pointer;
  }
  .p360-sat-pay.is-disabled{ opacity:.4; cursor:default; }

  /* Responsive */
  @media (max-width: 1100px){
    .p360-theme-label{ display:none; } /* compacta */
    .p360-logo{ max-width:150px; height:30px; }
    .p360-env, .p360-kbd{ display:none; }
    .p360-search{ width:100%; }
  }

  @media (max-width: 980px){
    .p360-h-grid{
      grid-template-columns: 1fr;
      gap:10px;
      padding: calc(6px + var(--safe-top, 0px)) 10px 10px;
    }
    .p360-h-left, .p360-h-right{ width:100%; justify-content:space-between; }
    .p360-h-center{ width:100%; justify-content:center; }
    .p360-search{ width:100%; }
    .p360-hamb{ display:inline-flex; }
  }

  @media (max-width: 520px){
    .p360-logo{ max-width:128px; height:26px; }
    .p360-btn{ height:36px; padding:0 9px; }
  }
</style>

<script>
(function(){
  'use strict';

  const root = document.documentElement;
  const header = document.getElementById('topbar');
  if(!header) return;

  // ---------- Helpers ----------
  const qs = (sel, el=document)=> el.querySelector(sel);
  const qsa = (sel, el=document)=> Array.from(el.querySelectorAll(sel));

  // Close other <details> when one opens + close on outside click
  const details = qsa('details.p360-dd', header);
  details.forEach(d=>{
    d.addEventListener('toggle', ()=>{
      if(!d.open) return;
      details.forEach(o=>{ if(o!==d) o.open=false; });
    });
  });
  document.addEventListener('click', (e)=>{
    if(header.contains(e.target)) return;
    details.forEach(d=> d.open=false);
  }, {capture:true});

  // ---------- Theme button ----------
  const btnTheme = document.getElementById('btnTheme');
  const themeLabel = document.getElementById('themeLabel');

  function currentTheme(){
    return (root.dataset.theme || (root.classList.contains('theme-dark') ? 'dark' : 'light')) === 'dark' ? 'dark' : 'light';
  }
  function paintThemeLabel(){
    const t = currentTheme();
    if(themeLabel) themeLabel.textContent = (t === 'dark') ? 'Modo oscuro' : 'Modo claro';
    if(btnTheme) btnTheme.setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false');
  }
  paintThemeLabel();

  btnTheme && btnTheme.addEventListener('click', ()=>{
    if (window.P360 && typeof window.P360.toggleTheme === 'function') {
      window.P360.toggleTheme();
    } else {
      // fallback mínimo
      const t = currentTheme() === 'dark' ? 'light' : 'dark';
      root.dataset.theme = t;
      root.classList.toggle('theme-dark', t === 'dark');
      root.classList.toggle('theme-light', t !== 'dark');
      try{ localStorage.setItem('p360.theme', t); }catch(_){}
    }
    paintThemeLabel();
  });

  // ---------- Mobile sidebar toggle ----------
  const btnSidebar = document.getElementById('btnSidebar');
  function isDesktop(){ return window.matchMedia('(min-width:1024px)').matches; }

  btnSidebar && btnSidebar.addEventListener('click', ()=>{
    if (window.P360?.sidebar?.toggle) {
      window.P360.sidebar.toggle();
    } else {
      // fallback: en móvil abre/cierra por class en body (tu sidebar Nebula lo usa)
      if (!isDesktop()) document.body.classList.toggle('sidebar-open');
    }
    // aria-expanded (móvil)
    const open = document.body.classList.contains('sidebar-open');
    btnSidebar.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // ---------- Heartbeat ----------
  const hbUrl = header.getAttribute('data-heartbeat-url') || '';
  const hbDot = document.getElementById('hbDot');

  async function ping(){
    if(!hbUrl || !hbDot) return;
    try{
      const r = await fetch(hbUrl, {method:'GET', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
      hbDot.classList.remove('ok','warn','fail');
      if(r.ok){
        hbDot.classList.add('ok');
      } else {
        hbDot.classList.add('warn');
      }
    }catch(_){
      hbDot.classList.remove('ok','warn');
      hbDot.classList.add('fail');
    }
  }
  ping();
  setInterval(ping, 15000);

})();
</script>
