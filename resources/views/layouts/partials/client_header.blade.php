{{-- resources/views/layouts/partials/client_header.blade.php
v3.11 · dropdown perfil modernizado + “Mi cuenta” debajo de “Mi perfil” + estilos mejorados --}}
@php
  use Illuminate\Support\Facades\Route;
  use App\Http\Controllers\Cliente\HomeController as ClientHome;

  $isFirstPassword   = request()->routeIs('cliente.password.first');
  $theme             = $isFirstPassword ? 'light' : session('client_ui.theme', 'light');
  $routeThemeSwitch  = Route::has('cliente.ui.theme.switch') ? route('cliente.ui.theme.switch') : '';

  // LOGOS (archivos nuevos sin espacios)
  $logoLightUrl = '/assets/client/p360-black.png';
  $logoDarkUrl  = '/assets/client/p360-white.png';

  // SPRITE
  $spriteUrl    = '/assets/client/icons.svg';
  $spriteInline = '';
  try {
      $p = public_path(ltrim($spriteUrl,'/'));
      if (is_file($p)) {
        $raw = file_get_contents($p) ?: '';
        if ($raw && preg_match('~<svg[^>]*>(.*)</svg>~si', $raw, $m)) {
          $spriteInline = trim($m[1]);
        }
      }
  } catch (\Throwable $e) {}

  $brandSrc = $theme === 'dark' ? $logoDarkUrl : $logoLightUrl;

  // Usuario / cuenta
  $u      = auth('web')->user();
  $c      = $cuenta ?? ($u?->cuenta ?? null);
  $name   = $u?->nombre ?? $u?->name ?? $u?->email ?? 'Cuenta';
  $email  = $u?->email ?? '';

  // ===== Resumen unificado de cuenta (admin.accounts) =====
  $summary = app(ClientHome::class)->buildAccountSummary();

  $planRaw  = strtoupper((string)($summary['plan'] ?? ($c->plan_actual ?? $c->plan ?? 'FREE')));
  $planSlug = strtolower($planRaw);

  $isProPlan = (bool)($summary['is_pro'] ?? in_array(
      $planSlug,
      ['pro','premium','empresa','business'],
      true
  ));

  // Lo que se muestra en el chip del header
  $plan      = $planRaw;
  $planBadge = $isProPlan ? 'PRO' : $planRaw;

  // Rutas de navegación base
  $rtHome     = Route::has('cliente.home') ? route('cliente.home') : url('/cliente');
  $rtSearchTo = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtAlerts   = Route::has('cliente.alertas')
                  ? route('cliente.alertas')
                  : (Route::has('cliente.notificaciones') ? route('cliente.notificaciones') : '#');
  $rtChat     = Route::has('cliente.soporte.chat')
                  ? route('cliente.soporte.chat')
                  : (Route::has('cliente.chat') ? route('cliente.chat') : '#');
  $rtCart     = Route::has('cliente.marketplace')
                  ? route('cliente.marketplace')
                  : (Route::has('cliente.tienda') ? route('cliente.tienda') : '#');
  $rtLogout   = Route::has('cliente.logout') ? route('cliente.logout') : url('/cliente/logout');

  // PERFIL (Mi perfil)
  $rtPerfil = Route::has('cliente.perfil')
      ? route('cliente.perfil')
      : url('cliente/perfil');

  // MI CUENTA (nuevo)
  $rtMiCuenta = Route::has('cliente.mi_cuenta')
      ? route('cliente.mi_cuenta')
      : url('cliente/mi-cuenta');

  // CONFIGURACIÓN (varios posibles nombres; NUNCA cae a perfil)
  $rtSettings = null;
  foreach ([
      'cliente.settings',
      'cliente.configuracion',
      'cliente.cuenta.configuracion',
      'cliente.account.settings',
      'cliente.cuenta.ajustes',
  ] as $routeName) {
      if (Route::has($routeName)) {
          $rtSettings = route($routeName);
          break;
      }
  }
  if (!$rtSettings) {
      $rtSettings = url('cliente/configuracion');
  }

  // Ruta para ir al módulo SAT / carrito SAT al darle "Proceder a pago"
  $rtSatPay = Route::has('cliente.sat.cart.index')
      ? route('cliente.sat.cart.index')
      : (Route::has('cliente.sat.index')
          ? route('cliente.sat.index')
          : url('cliente/sat'));

  // Badges (opcionales)
  $notifCount = (int)($notifCount ?? 0);
  $chatCount  = (int)($chatCount  ?? 0);
  $cartCount  = (int)($cartCount  ?? 0);

  // Href para <use/> del sprite
  $uHref = function(string $id) use ($spriteInline, $spriteUrl) {
    return $spriteInline !== '' ? "#{$id}" : ($spriteUrl . "#{$id}");
  };

  // Inicial de avatar
  $initial = strtoupper(mb_substr(trim((string)($u?->nombre ?? $u?->email ?? '')), 0, 1));

  // Nombre de cuenta mostrado
  $acctLabel = $u?->razon_social ?? $u?->nombre ?? $u?->email ?? 'Cuenta cliente';
@endphp


<header id="p360-client" class="topbar" role="banner"
        style="grid-template-columns:auto 1fr auto; gap:16px; height:var(--header);"
        data-theme-switch="{{ $routeThemeSwitch }}"
        data-logo-light="{{ $logoLightUrl }}"
        data-logo-dark="{{ $logoDarkUrl }}"
        data-icon-sprite="{{ $spriteUrl }}"
        data-force-light="{{ $isFirstPassword ? '1' : '0' }}">
  @if($spriteInline !== '')
    <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">{!! $spriteInline !!}</svg>
  @endif

  {{-- Marca (logo proporcional controlado por --logo-h) --}}
  <div class="brand" style="display:flex;align-items:center;gap:10px">
    <a href="{{ $rtHome }}" aria-label="Inicio" style="display:flex;align-items:center;gap:8px;">
      <img id="brandLogo" class="brand-logo" src="{{ $brandSrc }}" alt="Pactopia360"
           data-src-light="{{ $logoLightUrl }}" data-src-dark="{{ $logoDarkUrl }}">
    </a>
  </div>

  {{-- Buscador (centrado en la franja superior) --}}
  <form class="searchbar"
        role="search"
        action="{{ $rtSearchTo }}"
        method="GET"
        aria-label="Buscar CFDI o datos"
        style="justify-self:center;">
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('search') }}"/></svg>
    <input name="q" placeholder="Buscar en tu cuenta (CFDI, receptor, folio…)" autocomplete="off" />
  </form>

  {{-- Acciones header (SIN botón de menú) --}}
  <div class="top-actions" style="display:flex; align-items:center; gap:14px;">
    {{-- Tema --}}
    <button id="btnTheme" class="btn icon" type="button"
            title="{{ $isFirstPassword ? 'Tema fijo en claro en esta pantalla' : 'Cambiar tema' }}"
            aria-pressed="{{ $theme === 'dark' ? 'true' : 'false' }}"
            {{ $isFirstPassword ? 'disabled style=opacity:.35;cursor:not-allowed' : '' }}>
      @if ($theme === 'dark')
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('moon') }}"/></svg>
      @else
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('sun') }}"/></svg>
      @endif
    </button>

    {{-- Notificaciones --}}
    <a class="btn icon" href="{{ $rtAlerts }}" title="Notificaciones" aria-label="Notificaciones">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('bell') }}"/></svg>
      @if($notifCount > 0)<span class="badge" aria-label="{{ $notifCount }} notificaciones">{{ $notifCount }}</span>@endif
    </a>

    {{-- Chat/Soporte --}}
    <a class="btn icon" href="{{ $rtChat }}" title="Mensajes con soporte" aria-label="Mensajes con soporte">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('chat') }}"/></svg>
      @if($chatCount > 0)<span class="badge" aria-label="{{ $chatCount }} mensajes">{{ $chatCount }}</span>@endif
    </a>

    {{-- ===== MINI CARRITO SAT (estilo Amazon: botón amarillo + badge rojo) ===== --}}
    <div id="satCartHeader" class="sat-cart-header" style="display:none;">
      <a href="{{ Route::has('cliente.sat.cart.index') ? route('cliente.sat.cart.index') : $rtSatPay }}"
         class="sat-cart-header-btn"
         title="Carrito SAT"
         aria-label="Carrito SAT">
        <svg class="sat-cart-header-ico" viewBox="0 0 24 24" aria-hidden="true">
          <use href="{{ asset('assets/client/icons.svg#cart') }}" />
        </svg>
        <span id="satCartHeaderCount" class="sat-cart-header-badge">0</span>
      </a>
    </div>
    {{-- ===== / MINI CARRITO SAT ===== --}}

    {{-- Cuenta / Perfil --}}
    <div class="account" style="display:flex; align-items:center; gap:10px;">
      <div class="acct-meta">
        <span class="acct-name">{{ $acctLabel }}</span>
        <span class="acct-plan" title="Plan actual: {{ $plan }}">{{ $planBadge }}</span>
      </div>

      <div class="menu-profile" style="position:relative">
        <button id="btnProfile" class="btn icon" aria-haspopup="menu" aria-expanded="false"
                aria-controls="menuProfile" title="Cuenta">
          <span class="avatar" aria-hidden="true">{{ $initial ?: 'U' }}</span>
        </button>

        {{-- Dropdown modernizado --}}
        <div id="menuProfile" class="dropdown dd-profile" role="menu" aria-labelledby="btnProfile" hidden>
          <div class="dd-head" role="none">
            <div class="dd-ava" aria-hidden="true">{{ $initial ?: 'U' }}</div>
            <div class="dd-who">
              <div class="dd-name">{{ $name }}</div>
              <div class="dd-mail">{{ $email ?: '—' }}</div>
            </div>
            <span class="dd-chip" title="Plan">{{ $planBadge }}</span>
          </div>

          <div class="dd-section" role="none">
            <div class="dd-label" role="none">Cuenta</div>

            <a class="dd-item" href="{{ $rtPerfil }}" role="menuitem">
              <svg class="dd-ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('user') }}"/></svg>
              <span>Mi perfil</span>
            </a>

            {{-- NUEVO: Mi cuenta (debajo de Mi perfil) --}}
            <a class="dd-item" href="{{ $rtMiCuenta }}" role="menuitem">
              <svg class="dd-ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('credit-card') }}"/></svg>
              <span>Mi cuenta</span>
            </a>

            <a class="dd-item" href="{{ $rtSettings }}" role="menuitem">
              <svg class="dd-ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('settings') }}"/></svg>
              <span>Configuración</span>
            </a>
          </div>

          <div class="dd-sep" role="none"></div>

          <form action="{{ $rtLogout }}" method="POST" role="none">
            @csrf
            <button type="submit" class="dd-item dd-danger" role="menuitem">
              <svg class="dd-ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('logout') }}"/></svg>
              <span>Cerrar sesión</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<style>
  :root{ --header-h:64px; --header:64px; --ico:20px; }

  /* Tamaños adaptativos del logo (usa --logo-h del core) */
  .brand-logo{ height: var(--logo-h); width:auto; display:block }
  @media (max-width: 900px){ :root{ --logo-h:34px } }
  @media (max-width: 520px){ :root{ --logo-h:28px } }

  .topbar, .topbar * {
    font-family: 'Poppins', var(--font-sans, ui-sans-serif), system-ui, -apple-system,
                 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
  }
  .topbar{
    position: sticky; top:0; z-index:50; display:grid; align-items:center;
    padding:12px 20px;
    background: color-mix(in oklab, var(--card,#fff) 92%, transparent);
    border-bottom:1px solid var(--bd,#e5e7eb); backdrop-filter: saturate(140%) blur(6px);
  }
  html.theme-dark .topbar{
    background: color-mix(in oklab, #0b1220 86%, transparent);
    border-bottom-color: rgba(255,255,255,.12);
  }

 .ico{
    width:var(--ico);
    height:var(--ico);
    display:block;
    color: var(--text,#0f172a);
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  /* Fuerza que el contenido referenciado por <use> también herede color */
  .ico use,
  .dd-ico use,
  .sat-cart-header-ico use{
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  /* Íconos del dropdown */
  .dd-ico{
    width:18px;
    height:18px;
    opacity:.85;
    flex:0 0 auto;
    color: inherit;
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  /* Ícono del carrito SAT */
  .sat-cart-header-ico{
    width:20px;
    height:20px;
    color:#111827;
    fill: currentColor !important;
    stroke: currentColor !important;
  }
  html.theme-dark .sat-cart-header-ico{
    color:#0b1220;
  }


  .searchbar{
    display:flex; align-items:center; gap:8px;
    border:1px solid var(--bd,#e5e7eb); border-radius:999px;
    background: var(--chip, #f8fafc);
    padding:0 12px; height:40px; max-width:620px; width:100%;
    margin-inline:auto;
  }
  html.theme-dark .searchbar{
    background: color-mix(in oklab, #fff 6%, transparent);
  }
  .searchbar .ico{
    color: color-mix(in oklab, var(--text,#0f172a) 60%, transparent);
  }
  .searchbar input{
    all:unset; flex:1; font-weight:600; color:var(--text,#0f172a); font-size:14px;
  }

  .btn.icon{
    position:relative; display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:12px;
    border:1px solid var(--bd,#e5e7eb); background:var(--card,#fff); cursor:pointer;
  }
  html.theme-dark .btn.icon{
    background: color-mix(in oklab, #fff 6%, transparent);
    border-color: rgba(255,255,255,.12);
  }

  .badge{
    position:absolute; top:-6px; right:-6px; min-width:18px; height:18px;
    padding:0 6px; border-radius:999px; background:#ef4444; color:#fff;
    font:800 11px/18px system-ui; text-align:center;
    border:2px solid color-mix(in oklab, var(--card,#fff) 92%, transparent);
  }

  .acct-meta{
    display:flex; flex-direction:column; align-items:flex-end; line-height:1.1; min-width:160px;
  }
  .acct-name{ font-weight:700; color:var(--text,#0f172a); }
  .acct-plan{ font-size:12px; color:var(--muted,#6b7280); font-weight:700; }

  .avatar{
    width:36px; height:36px; border-radius:999px; display:grid; place-items:center;
    background: var(--brand,#E11D48); color:#fff; font-weight:800; font-size:14px;
  }

  @media (max-width: 900px){
    .acct-meta{ display:none; }
    .searchbar{ max-width:100%; }
  }

  /* ========== MINI CARRITO SAT EN HEADER (estilo Amazon) ========== */
  .sat-cart-header{ display:flex; align-items:center; }
  .sat-cart-header-btn{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:40px;
    height:40px;
    border-radius:12px;
    border:1px solid #facc15;
    background:#ffd814;
    padding:0;
    cursor:pointer;
    transition: transform .08s ease, box-shadow .08s ease, filter .08s ease;
  }
  .sat-cart-header-btn:hover{
    filter:brightness(0.97);
    box-shadow:0 2px 0 #f59e0b;
    transform:translateY(-1px);
  }
  .sat-cart-header-btn:active{ transform:translateY(0); box-shadow:none; }
  html.theme-dark .sat-cart-header-btn{ border-color:#eab308; background:#facc15; }
  .sat-cart-header-ico{ width:20px; height:20px; color:#111827; }
  .sat-cart-header-badge{
    position:absolute; bottom:-3px; right:-3px;
    min-width:18px; height:18px; padding:0 5px;
    border-radius:999px;
    background:#ef4444; color:#fff;
    font:700 11px/18px system-ui;
    border:2px solid #ffffff;
  }

  /* ========== Dropdown perfil modernizado ========== */
  .dropdown.dd-profile{
    position:absolute; right:0; top:calc(100% + 10px);
    width: 280px;
    background: color-mix(in oklab, var(--card,#fff) 96%, transparent);
    color: var(--text,#0f172a);
    border: 1px solid color-mix(in oklab, var(--bd,#e5e7eb) 90%, transparent);
    border-radius: 16px;
    box-shadow: 0 18px 46px rgba(2,6,23,.16);
    padding: 10px;
    z-index: 60;
    transform-origin: top right;
  }
  .dropdown[hidden]{ display:none !important; }
  html.theme-dark .dropdown.dd-profile{
    background: color-mix(in oklab, #0b1220 92%, transparent);
    border-color: rgba(255,255,255,.12);
    color:#e5e7eb;
    box-shadow: 0 18px 46px rgba(0,0,0,.40);
  }

  .dd-head{
    display:flex; align-items:center; gap:10px;
    padding:10px 10px 12px;
    border-radius:14px;
    background: linear-gradient(180deg,
      color-mix(in oklab, var(--brand,#E11D48) 10%, transparent),
      transparent);
    border: 1px solid color-mix(in oklab, var(--brand,#E11D48) 18%, transparent);
  }
  html.theme-dark .dd-head{
    background: linear-gradient(180deg, rgba(255,255,255,.06), transparent);
    border-color: rgba(255,255,255,.10);
  }

  .dd-ava{
    width:40px; height:40px; border-radius:999px;
    display:grid; place-items:center;
    background: var(--brand,#E11D48);
    color:#fff;
    font-weight:900;
    letter-spacing:.02em;
  }
  .dd-who{ min-width:0; flex:1; }
  .dd-name{
    font-weight:900;
    font-size:13px;
    line-height:1.1;
    color:inherit;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .dd-mail{
    margin-top:2px;
    font-size:11px;
    font-weight:700;
    opacity:.75;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .dd-chip{
    font-size:11px;
    font-weight:900;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid color-mix(in oklab, var(--brand,#E11D48) 35%, transparent);
    background: color-mix(in oklab, var(--brand,#E11D48) 14%, transparent);
    white-space:nowrap;
  }

  .dd-section{ padding:10px 4px 6px; }
  .dd-label{
    font-size:11px;
    font-weight:900;
    letter-spacing:.06em;
    text-transform:uppercase;
    opacity:.7;
    padding:0 8px 8px;
  }

  .dd-item{
    display:flex;
    align-items:center;
    gap:10px;
    width:100%;
    text-decoration:none;
    color:inherit;
    background:transparent;
    border:0;
    cursor:pointer;
    padding:10px 10px;
    border-radius:12px;
    font-weight:800;
    text-align:left;
  }
  .dd-ico{ width:18px; height:18px; opacity:.85; flex:0 0 auto; }
  .dd-item:hover{
    background: color-mix(in oklab, #0b1220 5%, transparent);
  }
  html.theme-dark .dd-item:hover{
    background: rgba(255,255,255,.07);
  }

  .dd-sep{
    height:1px;
    margin:8px 6px;
    background: color-mix(in oklab, var(--bd,#e5e7eb) 70%, transparent);
  }
  html.theme-dark .dd-sep{ background: rgba(255,255,255,.10); }

  .dd-danger{
    color: #b91c1c;
  }
  html.theme-dark .dd-danger{ color:#fca5a5; }
  .dd-danger:hover{
    background: color-mix(in oklab, #ef4444 12%, transparent);
  }
  html.theme-dark .dd-danger:hover{
    background: rgba(239,68,68,.16);
  }
</style>

<script>
window.addEventListener('DOMContentLoaded', () => {
  const html     = document.documentElement;
  const root     = document.getElementById('p360-client');
  const btnTheme = document.getElementById('btnTheme');
  const logo     = document.getElementById('brandLogo');

  const route      = root?.getAttribute('data-theme-switch') || '';
  const lightSrc   = root?.getAttribute('data-logo-light') || '';
  const darkSrc    = root?.getAttribute('data-logo-dark')  || '';
  const sprite     = root?.getAttribute('data-icon-sprite') || '';
  const csrf       = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const forceLight = (root?.getAttribute('data-force-light') === '1');

  const hasInlineSprite = !!document.querySelector('svg[aria-hidden="true"]');
  const useHref = (id) => (hasInlineSprite ? `#${id}` : `${sprite}#${id}`);

  function setLogoForTheme(next){
    if (logo && lightSrc && darkSrc) {
      logo.src = (next === 'dark') ? darkSrc : lightSrc;
    }
  }
  function setTheme(next){
    html.setAttribute('data-theme', next);
    html.classList.remove('theme-dark','theme-light');
    html.classList.add(next === 'dark' ? 'theme-dark' : 'theme-light');
    if (btnTheme){
      btnTheme.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
      btnTheme.innerHTML = next === 'dark'
        ? `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="${useHref('moon')}"/></svg>`
        : `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="${useHref('sun')}"/></svg>`;
    }
    setLogoForTheme(next);
  }

  const initial = forceLight ? 'light' : (html.getAttribute('data-theme') || 'light');
  setTheme(initial);

  btnTheme?.addEventListener('click', async () => {
    if (forceLight) return;
    const next = (html.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
    setTheme(next);
    if(route){
      try{
        await fetch(route, {
          method:'POST',
          headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ theme: next })
        });
      }catch(e){
        try { localStorage.setItem('p360_client_theme', next); } catch(_){}
      }
    }else{
      try { localStorage.setItem('p360_client_theme', next); } catch(_){}
    }
  });

  // Altura dinámica del header → variables CSS
  const h = root?.getBoundingClientRect().height;
  if (h){
    const px = `${Math.round(h)}px`;
    html.style.setProperty('--header', px);
    html.style.setProperty('--header-h', px);
  }

  // Dropdown perfil (abrir/cerrar)
  const btnProfile  = document.getElementById('btnProfile');
  const menuProfile = document.getElementById('menuProfile');

  function closeProfile(){
    if(menuProfile){
      menuProfile.hidden = true;
      btnProfile?.setAttribute('aria-expanded','false');
    }
  }

  btnProfile?.addEventListener('click', () => {
    if(!menuProfile) return;
    const open = menuProfile.hidden;
    menuProfile.hidden = !open;
    btnProfile.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if(menuProfile?.hidden) return;
    if(e.target.closest('.menu-profile')) return;
    closeProfile();
  });

  document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') closeProfile();
  });
});
</script>
