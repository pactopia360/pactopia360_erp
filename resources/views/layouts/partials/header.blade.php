{{-- resources/views/layouts/partials/header.blade.php (v3.6 · logo + carrito SAT · SAFE routes + FULL width + no root header-h override) --}}
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

  // Carrito de descargas SAT (lado cliente). Ajusta el nombre de ruta si usas otro.
  $cartUrl = Route::has('cliente.carrito.index')
      ? route('cliente.carrito.index')
      : (Route::has('cliente.cart.index') ? route('cliente.cart.index') : '#');

  // URL SAT "segura" (evita route() directo sin exists)
  $satUrl = Route::has('cliente.sat.index')
      ? route('cliente.sat.index')
      : (Route::has('cliente.sat') ? route('cliente.sat') : '#');

  $envName = app()->environment();
  $unread  = (int) (session('admin_unread_notifications', 0));
@endphp

<header id="topbar" class="header" role="banner"
        data-search-url="{{ $searchUrl }}"
        @if($notifCountUrl) data-notif-count-url="{{ $notifCountUrl }}" @endif
        @if($notifListUrl)  data-notif-list-url="{{ $notifListUrl }}"   @endif
        @if($heartbeatUrl)  data-heartbeat-url="{{ $heartbeatUrl }}"    @endif
        data-env="{{ $envName }}">

  <div class="header-left">
    <a class="brand-link" href="{{ $brandUrl }}" aria-label="Inicio">
      <img class="brand-logo brand-light" src="{{ $logoLight }}" alt="Pactopia360" width="160" height="32" decoding="async" fetchpriority="high">
      <img class="brand-logo brand-dark"  src="{{ $logoDark  }}"  alt="Pactopia360" width="160" height="32" decoding="async">
    </a>

    <span class="env-badge" title="Entorno de ejecución">
      <span class="env-text">{{ strtoupper($envName) }}</span>
      <span id="hbDot" class="hb-dot" aria-label="Estado del servidor" title="Estado"></span>
    </span>
  </div>

  <div class="header-search">
    <form class="search-wrap" role="search" method="get"
          action="{{ $searchUrl }}"
          @if($searchUrl !== '#') data-pjax-form @endif
          onsubmit="return this.q.value.trim().length>0">
      <span class="search-icon" aria-hidden="true">🔎</span>
      <input id="globalSearch" name="q" type="search"
             placeholder="Buscar en el panel… (Ctrl + K o /)"
             autocomplete="off" aria-label="Buscar en el panel" enterkeyhint="search">
      <kbd class="kbd" aria-hidden="true">Ctrl + K</kbd>
    </form>
  </div>

  <div class="header-right">
    <details class="notif-menu" data-menu="quick">
      <summary class="notif-btn" title="Acciones rápidas" aria-label="Acciones rápidas">⚡</summary>
      <div class="dropdown" style="min-width:260px">
        <div class="dropdown-header">Acciones rápidas</div>
        <div class="dropdown-body">
          <nav class="menu-vert">
            @if(Route::has('admin.pagos.create'))    <a href="{{ route('admin.pagos.create') }}">Nuevo pago</a> @endif
            @if(Route::has('admin.clientes.create')) <a href="{{ route('admin.clientes.create') }}">Nuevo cliente</a> @endif
            @if(Route::has('admin.reportes.index'))  <a href="{{ route('admin.reportes.index') }}">Reportes</a> @endif
            @if(Route::has('admin.home'))            <a href="{{ route('admin.home') }}">Home</a> @endif
            @if(Route::has('admin.dashboard'))       <a href="{{ route('admin.dashboard') }}">Dashboard</a> @endif
          </nav>
        </div>
      </div>
    </details>

    <button id="btnTheme" class="theme-btn" type="button" aria-label="Cambiar tema" title="Cambiar tema" aria-pressed="false">
      <span class="ico" aria-hidden="true">🌓</span>
      <span id="themeLabel" aria-live="polite">Modo claro</span>
    </button>

    <details class="notif-menu" data-menu="notifications">
      <summary class="notif-btn" title="Notificaciones" aria-label="Notificaciones">
        🔔
        <span class="badge" id="notifBadge" aria-label="{{ $unread }} sin leer" @if($unread<=0) hidden @endif>{{ $unread>99?'99+':$unread }}</span>
      </summary>
      <div class="dropdown" style="min-width:320px">
        <div class="dropdown-header">Notificaciones</div>
        <div class="dropdown-body" id="p360NotifBody" data-state="idle">
          @if($unread>0)
            <p>Tienes {{ $unread }} notificaciones pendientes.</p>
            @if(Route::has('admin.notificaciones'))
              <a class="logout-btn" href="{{ route('admin.notificaciones') }}">Ver todas</a>
            @endif
          @else
            <p class="muted">Sin notificaciones.</p>
          @endif
        </div>
      </div>
    </details>

    {{-- CARRITO SAT (contador global) --}}
    <div id="satCartHeader"
         class="sat-cart-header"
         style="display:none;align-items:center;gap:6px;">
      {{-- contador / acceso rápido --}}
      <button type="button"
              class="theme-btn cart-btn"
              title="Carrito de descargas SAT"
              aria-label="Carrito de descargas SAT"
              onclick="window.location.href='{{ $satUrl !== '#' ? $satUrl : $cartUrl }}#block-downloads'">
        <span aria-hidden="true">🛒</span>
        <span class="cart-pill">
          <span id="satCartHeaderCount">0</span> ·
          <span id="satCartHeaderTotal">$0.00</span>
        </span>
      </button>

      {{-- botón proceder a pago --}}
      <button type="button"
              class="sat-cart-header-pay is-disabled"
              id="satCartHeaderPay"
              disabled
              onclick="window.location.href='{{ $satUrl !== '#' ? $satUrl : $cartUrl }}#block-downloads'">
        Proceder a pago
      </button>
    </div>

    <button id="btnNovaBot" class="theme-btn" type="button" title="Abrir asistente" aria-label="Abrir asistente">
      🤖 <span class="sr-only">Asistente</span>
    </button>

    <details class="avatar-menu" data-menu="profile">
      <summary class="theme-btn" aria-label="Menú de usuario" style="gap:10px">
        <img class="avatar-img" src="{{ $user?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($userName).'&background=0D8ABC&color=fff' }}" alt="">
        <span class="who" style="display:flex;flex-direction:column;line-height:1;min-width:0">
          <strong style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">{{ $userName }}</strong>
          <small class="muted" style="font-size:11px">Panel Administrativo</small>
        </span>
        <span aria-hidden="true">▾</span>
      </summary>
      <div class="dropdown">
        <div class="dropdown-header">{{ $userName }}</div>
        <div class="dropdown-body">
          <div class="dd-sub muted">{{ $userEmail }}</div>
          <nav class="menu-vert">
            @if($urlPerfil !== '#') <a href="{{ $urlPerfil }}">Mi perfil</a> @endif
            @if($urlConfig !== '#') <a href="{{ $urlConfig }}">Configuración</a> @endif
            <hr>
            @if($logoutRoute !== '#')
              <form method="post" action="{{ $logoutRoute }}" id="logoutForm">@csrf
                <button class="logout-btn w-100" type="submit" id="logoutBtn">Cerrar sesión</button>
              </form>
            @endif
          </nav>
        </div>
      </div>
    </details>
  </div>
</header>

<style>
  /* NOTA: NO redefinimos :root --header-h aquí para no pelear con el layout (syncHeaderHeight()) */
  .header{
    width:100% !important;
    max-width:none !important;
    margin:0 !important;

    position:sticky;
    top:0;
    z-index:1050;

    /* La altura real la calcula el layout por JS; aquí solo damos un mínimo */
    min-height:56px;

    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;

    padding: calc(6px + var(--safe-top, 0px)) 12px 8px;

    background:#fff;
    color:inherit;
    border-bottom:1px solid rgba(0,0,0,.08);
    backdrop-filter:saturate(180%) blur(6px);
  }
  html.theme-dark .header{ background:#0b1220; border-bottom-color:rgba(255,255,255,.12) }

  /* Importante: evitar que algún CSS global meta container/centrado */
  .header, .header *{ box-sizing:border-box; }
  .header-left, .header-search, .header-right{ min-width:0; }

  .header-left{display:flex;align-items:center;gap:12px;flex:0 0 auto;min-width:0}
  .header-right{display:flex;align-items:center;gap:10px;flex:0 0 auto}

  .brand-link{display:inline-flex;align-items:center;gap:10px;text-decoration:none}
  .brand-logo{height:32px;max-width:160px;width:auto}
  .brand-dark{display:none}
  html.theme-dark .brand-dark{display:inline}
  html.theme-dark .brand-light{display:none}

  /* Search: permite crecer, pero sin forzar max-width global (esto NO afecta el contenido principal) */
  .header-search{
    flex:1 1 auto;
    display:flex;
    justify-content:center;
    min-width:140px;
  }
  .search-wrap{
    display:flex;
    align-items:center;
    gap:8px;
    background:rgba(0,0,0,.04);
    border:1px solid rgba(0,0,0,.08);
    padding:6px 10px;
    border-radius:12px;

    width:100%;
    max-width: min(720px, 100%);
    min-height:38px;
    min-width:0;
  }
  html.theme-dark .search-wrap{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.08)}
  .search-wrap input{flex:1;border:0;outline:0;background:transparent;min-width:0}

  .kbd{font:600 10px/1 system-ui;background:rgba(0,0,0,.08);padding:2px 6px;border-radius:6px}
  html.theme-dark .kbd{background:rgba(255,255,255,.12)}

  .notif-menu{position:relative}
  .notif-menu>summary{list-style:none;cursor:pointer}
  .notif-menu>summary::-webkit-details-marker{display:none}
  .notif-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    height:38px;
    min-width:38px;
    padding:0 8px;
    border-radius:10px;
    border:1px solid rgba(0,0,0,.08);
    background:transparent
  }
  html.theme-dark .notif-btn{border-color:rgba(255,255,255,.12)}

  .dropdown{
    position:absolute;
    right:0;
    top:calc(100% + 8px);

    /* background: inherit puede heredar transparente en algunos skins; esto lo estabiliza */
    background: var(--topbar-bg, #fff);
    color: inherit;

    border:1px solid rgba(0,0,0,.12);
    border-radius:12px;
    box-shadow:0 12px 30px rgba(0,0,0,.12);
    padding:8px;
    min-width:220px;
    z-index:100
  }
  html.theme-dark .dropdown{border-color:rgba(255,255,255,.12)}

  .dropdown-header{font:700 12px/1 system-ui;margin:6px 4px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
  .menu-vert a{display:block;padding:6px 8px;border-radius:8px;text-decoration:none;color:inherit}
  .menu-vert a:hover{background:rgba(0,0,0,.06)}
  html.theme-dark .menu-vert a:hover{background:rgba(255,255,255,.08)}

  .badge{display:inline-flex;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#ef4444;color:#fff;font:700 10px/18px system-ui}
  .avatar-img{width:24px;height:24px;border-radius:9999px;object-fit:cover}

  .theme-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    height:38px;
    padding:0 10px;
    border-radius:10px;
    border:1px solid rgba(0,0,0,.08);
    background:transparent
  }
  html.theme-dark .theme-btn{border-color:rgba(255,255,255,.12)}

  .env-badge{display:inline-flex;align-items:center;gap:6px;font:600 11px/1 system-ui;padding:4px 8px;border-radius:9999px;background:rgba(0,0,0,.06)}
  html.theme-dark .env-badge{background:rgba(255,255,255,.08)}
  .env-text{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:12ch}
  .hb-dot{width:8px;height:8px;border-radius:9999px;display:inline-block;background:#9ca3af;box-shadow:0 0 0 0 rgba(16,185,129,.0)}
  .hb-dot.ok{background:#10b981;box-shadow:0 0 0 6px rgba(16,185,129,.18)}
  .hb-dot.warn{background:#f59e0b;box-shadow:0 0 0 6px rgba(245,158,11,.18)}
  .hb-dot.fail{background:#ef4444;box-shadow:0 0 0 6px rgba(239,68,68,.18)}

  /* Carrito SAT */
  .cart-btn{white-space:nowrap}
  .cart-pill{font-size:11px;display:inline-flex;align-items:center;gap:4px}

  .sat-cart-header-pay{
    font-size:11px;
    padding:0 12px;
    height:32px;
    border-radius:9999px;
    border:none;
    background:#22c55e;
    color:#fff;
    font-weight:600;
    cursor:pointer;
  }
  .sat-cart-header-pay.is-disabled{
    opacity:.4;
    cursor:default;
  }

  @media (max-width: 992px){
    .brand-logo{height:30px;max-width:150px}
    .env-badge,.kbd{display:none}
  }
  @media (max-width: 680px){
    .header{gap:8px}
    .header-right{gap:6px}
    .header-search{order:3;width:100%}
    .search-wrap{width:100%}
  }
  @media (max-width: 420px){
    .brand-logo{height:24px;max-width:120px}
    .notif-btn{min-width:36px;height:36px}
    .theme-btn{height:36px}
    .header{padding:8px 10px}
  }
</style>

<script>
/* (idéntico a tu v3: tema, búsqueda, heartbeat, notificaciones y logout) */
</script>
