{{-- resources/views/layouts/partials/client_header.blade.php
   Pactopia360 · Client Header · nuevo branding azul/blanco
   conserva dropdown, tema, badges, “Mi cuenta”, etc.
--}}
@php
  use Illuminate\Support\Facades\Route;
  use App\Http\Controllers\Cliente\HomeController as ClientHome;

  $isFirstPassword   = request()->routeIs('cliente.password.first');
  $theme             = $isFirstPassword ? 'light' : session('client_ui.theme', 'light');
  $routeThemeSwitch  = Route::has('cliente.ui.theme.switch') ? route('cliente.ui.theme.switch') : '';

  // Logo actual (claro/oscuro)
$logoLightUrl = '/assets/client/img/Pactopia - Letra AZUL.png';
$logoDarkUrl  = '/assets/client/img/Pactopia - Letra Blanca.png';

  // Sprite
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

  // Resumen unificado de cuenta
  $summary = app(ClientHome::class)->buildAccountSummary();

  $planRaw  = strtoupper((string)($summary['plan'] ?? ($c->plan_actual ?? $c->plan ?? 'FREE')));
  $planSlug = strtolower($planRaw);

  $isProPlan = (bool)($summary['is_pro'] ?? in_array(
      $planSlug,
      ['pro','premium','empresa','business'],
      true
  ));

  $plan      = $planRaw;
  $planBadge = $isProPlan ? 'PRO' : $planRaw;

  // Rutas
  $rtHome     = Route::has('cliente.home') ? route('cliente.home') : url('/cliente');
  $rtSearchTo = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtAlerts   = Route::has('cliente.alertas')
                  ? route('cliente.alertas')
                  : (Route::has('cliente.notificaciones') ? route('cliente.notificaciones') : '#');
  $rtChat     = Route::has('cliente.soporte.chat')
                  ? route('cliente.soporte.chat')
                  : (Route::has('cliente.chat') ? route('cliente.chat') : '#');
  $rtLogout   = Route::has('cliente.logout') ? route('cliente.logout') : url('/cliente/logout');

  // Perfil
  $rtPerfil = Route::has('cliente.perfil')
      ? route('cliente.perfil')
      : url('cliente/perfil');

  // Mi cuenta
  $rtMiCuenta = Route::has('cliente.mi_cuenta')
      ? route('cliente.mi_cuenta')
      : (Route::has('cliente.mi_cuenta.index') ? route('cliente.mi_cuenta.index') : url('cliente/mi-cuenta'));

  // Configuración
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

  // Ruta SAT carrito
  $rtSatPay = Route::has('cliente.sat.cart.index')
      ? route('cliente.sat.cart.index')
      : (Route::has('cliente.sat.index')
          ? route('cliente.sat.index')
          : url('cliente/sat'));

  // Badges
  $notifCount = (int)($notifCount ?? 0);
  $chatCount  = (int)($chatCount  ?? 0);
  $cartCount  = (int)($cartCount  ?? 0);

  // Helper href sprite
  $uHref = function(string $id) use ($spriteInline, $spriteUrl) {
    return $spriteInline !== '' ? "#{$id}" : ($spriteUrl . "#{$id}");
  };

  // Inicial avatar
  $initial = strtoupper(mb_substr(trim((string)($u?->nombre ?? $u?->email ?? 'U')), 0, 1));

  // Nombre de cuenta mostrado
  $acctLabel = $u?->razon_social ?? $u?->nombre ?? $u?->email ?? 'Cuenta cliente';
@endphp

<header
  id="p360-client"
  class="topbar"
  role="banner"
  data-theme-switch="{{ $routeThemeSwitch }}"
  data-logo-light="{{ $logoLightUrl }}"
  data-logo-dark="{{ $logoDarkUrl }}"
  data-icon-sprite="{{ $spriteUrl }}"
  data-force-light="{{ $isFirstPassword ? '1' : '0' }}"
>
  @if($spriteInline !== '')
    <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">{!! $spriteInline !!}</svg>
  @endif

  {{-- Marca --}}
  <div class="p360-head-brand">
    <a href="{{ $rtHome }}" aria-label="Inicio Pactopia360" class="p360-head-brand__link">
      <img
        id="brandLogo"
        class="p360-head-brand__logo"
        src="{{ $brandSrc }}"
        alt="Pactopia360"
        data-src-light="{{ $logoLightUrl }}"
        data-src-dark="{{ $logoDarkUrl }}"
      >
      <div class="p360-head-brand__meta">
        <span class="p360-head-brand__eyebrow">Pactopia360</span>
        <span class="p360-head-brand__title">Portal Usuario</span>
      </div>
    </a>
  </div>

  {{-- Buscador --}}
  <form
    class="p360-head-search"
    role="search"
    action="{{ $rtSearchTo }}"
    method="GET"
    aria-label="Buscar CFDI o datos"
  >
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('search') }}"/></svg>
    <input
      name="q"
      placeholder="Buscar en tu cuenta, CFDI, receptor, folio..."
      autocomplete="off"
    />
  </form>

  {{-- Acciones --}}
  <div class="p360-head-actions">
    {{-- Tema --}}
    <button
      id="btnTheme"
      class="p360-icon-btn"
      type="button"
      title="{{ $isFirstPassword ? 'Tema fijo en claro en esta pantalla' : 'Cambiar tema' }}"
      aria-pressed="{{ $theme === 'dark' ? 'true' : 'false' }}"
      {{ $isFirstPassword ? 'disabled style=opacity:.35;cursor:not-allowed' : '' }}
    >
      @if ($theme === 'dark')
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('moon') }}"/></svg>
      @else
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('sun') }}"/></svg>
      @endif
    </button>

    {{-- Notificaciones --}}
    <a class="p360-icon-btn" href="{{ $rtAlerts }}" title="Notificaciones" aria-label="Notificaciones">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('bell') }}"/></svg>
      @if($notifCount > 0)
        <span class="p360-badge" aria-label="{{ $notifCount }} notificaciones">{{ $notifCount }}</span>
      @endif
    </a>

    {{-- Chat --}}
    <a class="p360-icon-btn" href="{{ $rtChat }}" title="Mensajes con soporte" aria-label="Mensajes con soporte">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('chat') }}"/></svg>
      @if($chatCount > 0)
        <span class="p360-badge" aria-label="{{ $chatCount }} mensajes">{{ $chatCount }}</span>
      @endif
    </a>

    {{-- Carrito SAT --}}
    <div id="satCartHeader" class="p360-sat-cart" style="display:none;">
      <a
        href="{{ Route::has('cliente.sat.cart.index') ? route('cliente.sat.cart.index') : $rtSatPay }}"
        class="p360-sat-cart__btn"
        title="Carrito SAT"
        aria-label="Carrito SAT"
      >
        <svg class="p360-sat-cart__ico" viewBox="0 0 24 24" aria-hidden="true">
          <use href="{{ asset('assets/client/icons.svg#cart') }}" />
        </svg>
        <span id="satCartHeaderCount" class="p360-sat-cart__badge">0</span>
      </a>
    </div>

    {{-- Cuenta --}}
    <div class="p360-account">
      <div class="p360-account__meta">
        <span class="p360-account__name" title="{{ $acctLabel }}">{{ $acctLabel }}</span>
        <span class="p360-account__plan" title="Plan actual: {{ $plan }}">{{ $planBadge }}</span>
      </div>

      <div class="menu-profile" style="position:relative">
        <button
          id="btnProfile"
          class="p360-profile-btn"
          aria-haspopup="menu"
          aria-expanded="false"
          aria-controls="menuProfile"
          title="Cuenta"
        >
          <span class="p360-profile-btn__avatar" aria-hidden="true">{{ $initial ?: 'U' }}</span>
        </button>

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
  :root{
    --header-h:72px;
    --header:72px;
    --ico:20px;
    --logo-h:42px;
    --p360-hd-bg-light: rgba(255,255,255,.78);
    --p360-hd-bg-dark: rgba(8,18,38,.82);
    --p360-hd-bd-light: rgba(15,23,42,.08);
    --p360-hd-bd-dark: rgba(255,255,255,.08);
    --p360-hd-chip-light: rgba(255,255,255,.72);
    --p360-hd-chip-dark: rgba(255,255,255,.06);
    --p360-hd-brand: #2563eb;
    --p360-hd-brand-2: #60a5fa;
  }

  @media (max-width: 900px){ :root{ --logo-h:36px; } }
  @media (max-width: 520px){ :root{ --logo-h:30px; } }

  .topbar,
  .topbar *{
    font-family:'Poppins', var(--font-sans, ui-sans-serif), system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    box-sizing:border-box;
  }

  .topbar{
    position:sticky;
    top:0;
    z-index:50;
    display:grid;
    grid-template-columns:minmax(220px, auto) minmax(320px, 1fr) auto;
    align-items:center;
    gap:18px;
    min-height:var(--header);
    padding:12px 22px;
    backdrop-filter: blur(16px) saturate(140%);
    -webkit-backdrop-filter: blur(16px) saturate(140%);
    background:var(--p360-hd-bg-light);
    border-bottom:1px solid var(--p360-hd-bd-light);
    box-shadow:0 8px 28px rgba(15,23,42,.04);
  }

  html.theme-dark .topbar{
    background:var(--p360-hd-bg-dark);
    border-bottom-color:var(--p360-hd-bd-dark);
    box-shadow:0 12px 30px rgba(0,0,0,.18);
  }

  .p360-head-brand{
    min-width:0;
  }

  .p360-head-brand__link{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    min-width:0;
  }

  .p360-head-brand__logo{
    height:var(--logo-h);
    width:auto;
    display:block;
    object-fit:contain;
    filter: drop-shadow(0 4px 10px rgba(37,99,235,.12));
  }

  .p360-head-brand__meta{
    display:flex;
    flex-direction:column;
    min-width:0;
    line-height:1.05;
  }

  .p360-head-brand__eyebrow{
    font-size:11px;
    font-weight:800;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:var(--muted,#64748b);
  }

  .p360-head-brand__title{
    font-size:15px;
    font-weight:800;
    color:var(--text,#0f172a);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  html.theme-dark .p360-head-brand__title{
    color:#e8eefc;
  }

  .ico{
    width:var(--ico);
    height:var(--ico);
    display:block;
    color:var(--text,#0f172a);
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  .ico use,
  .dd-ico use,
  .p360-sat-cart__ico use{
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  .p360-head-search{
    display:flex;
    align-items:center;
    gap:10px;
    width:100%;
    max-width:760px;
    min-width:240px;
    height:46px;
    margin-inline:auto;
    padding:0 16px;
    border-radius:999px;
    border:1px solid rgba(37,99,235,.10);
    background:linear-gradient(180deg, rgba(255,255,255,.80), rgba(255,255,255,.64));
    box-shadow: inset 0 1px 0 rgba(255,255,255,.72), 0 10px 24px rgba(15,23,42,.04);
  }

  html.theme-dark .p360-head-search{
    background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
    border-color:rgba(255,255,255,.08);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.04), 0 10px 24px rgba(0,0,0,.12);
  }

  .p360-head-search .ico{
    color:color-mix(in oklab, var(--text,#0f172a) 60%, transparent);
    flex:0 0 auto;
  }

  .p360-head-search input{
    all:unset;
    flex:1;
    font-size:14px;
    font-weight:600;
    color:var(--text,#0f172a);
  }

  html.theme-dark .p360-head-search input{
    color:#e5eefc;
  }

  .p360-head-search input::placeholder{
    color:color-mix(in oklab, var(--muted,#64748b) 86%, transparent);
    font-weight:600;
  }

  .p360-head-actions{
    display:flex;
    align-items:center;
    gap:12px;
    justify-self:end;
  }

  .p360-icon-btn{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:42px;
    height:42px;
    border-radius:14px;
    border:1px solid rgba(37,99,235,.10);
    background:linear-gradient(180deg, rgba(255,255,255,.84), rgba(255,255,255,.70));
    box-shadow:0 10px 22px rgba(15,23,42,.04);
    cursor:pointer;
    text-decoration:none;
    transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
  }

  .p360-icon-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 14px 28px rgba(37,99,235,.10);
    border-color:rgba(37,99,235,.18);
  }

  html.theme-dark .p360-icon-btn{
    background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
    border-color:rgba(255,255,255,.08);
    box-shadow:0 10px 24px rgba(0,0,0,.14);
  }

  html.theme-dark .p360-icon-btn:hover{
    border-color:rgba(96,165,250,.22);
    box-shadow:0 14px 30px rgba(0,0,0,.22);
  }

  .p360-badge{
    position:absolute;
    top:-5px;
    right:-5px;
    min-width:18px;
    height:18px;
    padding:0 6px;
    border-radius:999px;
    background:#ef4444;
    color:#fff;
    font:800 11px/18px system-ui;
    text-align:center;
    border:2px solid rgba(255,255,255,.92);
  }

  html.theme-dark .p360-badge{
    border-color:#0b1220;
  }

  .p360-sat-cart{
    display:flex;
    align-items:center;
  }

  .p360-sat-cart__btn{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:42px;
    height:42px;
    border-radius:14px;
    border:1px solid #facc15;
    background:linear-gradient(180deg, #ffe277 0%, #ffd814 100%);
    box-shadow:0 10px 22px rgba(245,158,11,.18);
    padding:0;
    cursor:pointer;
    transition:transform .16s ease, box-shadow .16s ease, filter .16s ease;
  }

  .p360-sat-cart__btn:hover{
    filter:brightness(.985);
    transform:translateY(-1px);
    box-shadow:0 14px 28px rgba(245,158,11,.24);
  }

  .p360-sat-cart__ico{
    width:20px;
    height:20px;
    color:#111827;
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  .p360-sat-cart__badge{
    position:absolute;
    bottom:-4px;
    right:-4px;
    min-width:18px;
    height:18px;
    padding:0 5px;
    border-radius:999px;
    background:#ef4444;
    color:#fff;
    font:700 11px/18px system-ui;
    border:2px solid #fff;
  }

  .p360-account{
    display:flex;
    align-items:center;
    gap:10px;
    padding-left:4px;
  }

  .p360-account__meta{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    line-height:1.05;
    min-width:170px;
    max-width:260px;
  }

  .p360-account__name{
    font-size:13px;
    font-weight:800;
    color:var(--text,#0f172a);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:100%;
  }

  html.theme-dark .p360-account__name{
    color:#e8eefc;
  }

  .p360-account__plan{
    margin-top:4px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:5px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    letter-spacing:.04em;
    color:#0f4ed8;
    background:rgba(37,99,235,.10);
    border:1px solid rgba(37,99,235,.14);
  }

  html.theme-dark .p360-account__plan{
    color:#cfe1ff;
    background:rgba(96,165,250,.12);
    border-color:rgba(96,165,250,.16);
  }

  .p360-profile-btn{
    border:0;
    background:transparent;
    padding:0;
    cursor:pointer;
  }

  .p360-profile-btn__avatar{
    width:42px;
    height:42px;
    border-radius:999px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
    color:#fff;
    font-weight:900;
    font-size:14px;
    box-shadow:0 12px 26px rgba(37,99,235,.18);
    border:2px solid rgba(255,255,255,.88);
  }

  html.theme-dark .p360-profile-btn__avatar{
    border-color:rgba(10,23,48,.96);
  }

  .dropdown.dd-profile{
    position:absolute;
    right:0;
    top:calc(100% + 12px);
    width:292px;
    background:rgba(255,255,255,.92);
    color:var(--text,#0f172a);
    border:1px solid rgba(37,99,235,.10);
    border-radius:18px;
    box-shadow:0 20px 56px rgba(15,23,42,.16);
    padding:10px;
    z-index:60;
    transform-origin:top right;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
  }

  .dropdown[hidden]{
    display:none !important;
  }

  html.theme-dark .dropdown.dd-profile{
    background:rgba(9,19,38,.92);
    border-color:rgba(255,255,255,.08);
    color:#e5e7eb;
    box-shadow:0 20px 56px rgba(0,0,0,.40);
  }

  .dd-head{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    border-radius:16px;
    background:linear-gradient(180deg, rgba(37,99,235,.10), rgba(37,99,235,0));
    border:1px solid rgba(37,99,235,.12);
  }

  html.theme-dark .dd-head{
    background:linear-gradient(180deg, rgba(96,165,250,.10), rgba(96,165,250,0));
    border-color:rgba(255,255,255,.08);
  }

  .dd-ava{
    width:42px;
    height:42px;
    border-radius:999px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
    color:#fff;
    font-weight:900;
    letter-spacing:.02em;
    box-shadow:0 10px 24px rgba(37,99,235,.18);
  }

  .dd-who{
    min-width:0;
    flex:1;
  }

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
    margin-top:3px;
    font-size:11px;
    font-weight:700;
    opacity:.76;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .dd-chip{
    font-size:11px;
    font-weight:900;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(37,99,235,.20);
    background:rgba(37,99,235,.10);
    white-space:nowrap;
    color:#0f4ed8;
  }

  html.theme-dark .dd-chip{
    color:#d7e6ff;
    background:rgba(96,165,250,.12);
    border-color:rgba(96,165,250,.16);
  }

  .dd-section{
    padding:10px 4px 6px;
  }

  .dd-label{
    font-size:11px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
    opacity:.65;
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
    padding:11px 10px;
    border-radius:13px;
    font-weight:800;
    text-align:left;
  }

  .dd-ico{
    width:18px;
    height:18px;
    opacity:.9;
    flex:0 0 auto;
    color:inherit;
    fill: currentColor !important;
    stroke: currentColor !important;
  }

  .dd-item:hover{
    background:rgba(37,99,235,.06);
  }

  html.theme-dark .dd-item:hover{
    background:rgba(255,255,255,.06);
  }

  .dd-sep{
    height:1px;
    margin:8px 6px;
    background:rgba(15,23,42,.08);
  }

  html.theme-dark .dd-sep{
    background:rgba(255,255,255,.10);
  }

  .dd-danger{
    color:#b91c1c;
  }

  html.theme-dark .dd-danger{
    color:#fca5a5;
  }

  .dd-danger:hover{
    background:rgba(239,68,68,.10);
  }

  html.theme-dark .dd-danger:hover{
    background:rgba(239,68,68,.16);
  }

  @media (max-width: 1180px){
    .topbar{
      grid-template-columns:auto 1fr auto;
      gap:14px;
    }

    .p360-head-brand__meta{
      display:none;
    }

    .p360-account__meta{
      display:none;
    }
  }

  @media (max-width: 900px){
    .topbar{
      grid-template-columns:auto 1fr auto;
      padding:10px 14px;
      gap:10px;
    }

    .p360-head-search{
      min-width:0;
      max-width:100%;
      height:42px;
      padding:0 14px;
    }

    .p360-icon-btn,
    .p360-sat-cart__btn,
    .p360-profile-btn__avatar{
      width:40px;
      height:40px;
    }

    .dropdown.dd-profile{
      width:280px;
      right:0;
    }
  }

  @media (max-width: 640px){
    .topbar{
      grid-template-columns:auto 1fr auto;
    }

    .p360-head-search input{
      font-size:13px;
    }

    .p360-head-actions{
      gap:8px;
    }

    .p360-head-brand__logo{
      max-width:140px;
      height:auto;
      max-height:var(--logo-h);
    }
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
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ theme: next })
        });
      }catch(e){
        try { localStorage.setItem('p360_client_theme', next); } catch(_){}
      }
    }else{
      try { localStorage.setItem('p360_client_theme', next); } catch(_){}
    }
  });

  const h = root?.getBoundingClientRect().height;
  if (h){
    const px = `${Math.round(h)}px`;
    html.style.setProperty('--header', px);
    html.style.setProperty('--header-h', px);
  }

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
    const willOpen = menuProfile.hidden;
    menuProfile.hidden = !willOpen;
    btnProfile.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
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