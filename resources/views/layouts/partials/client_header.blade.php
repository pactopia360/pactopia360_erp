{{-- resources/views/layouts/partials/client_header.blade.php
    v3.8 · logo proporcional + sin botón menú + plan unificado + perfil/configuración separados --}}
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
  // Si no existe ninguna ruta nombrada, mandamos a /cliente/configuracion
  if (!$rtSettings) {
      $rtSettings = url('cliente/configuracion');
  }

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

  {{-- Buscador --}}
  <form class="searchbar" role="search" action="{{ $rtSearchTo }}" method="GET" aria-label="Buscar CFDI o datos">
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

    {{-- Marketplace --}}
    <a class="btn icon" href="{{ $rtCart }}" title="Marketplace" aria-label="Marketplace">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('cart') }}"/></svg>
      @if($cartCount > 0)<span class="badge" aria-label="{{ $cartCount }} artículos">{{ $cartCount }}</span>@endif
    </a>

    {{-- Cuenta / Perfil --}}
    <div class="account" style="display:flex; align-items:center; gap:10px;">
      <div class="acct-meta">
        <span class="acct-name">{{ $u?->razon_social ?? $u?->nombre ?? $u?->email ?? 'Cuenta cliente' }}</span>
        <span class="acct-plan" title="Plan actual: {{ $plan }}">{{ $planBadge }}</span>
      </div>

      <div class="menu-profile" style="position:relative">
        <button id="btnProfile" class="btn icon" aria-haspopup="menu" aria-expanded="false"
                aria-controls="menuProfile" title="Cuenta">
          <span class="avatar" aria-hidden="true">{{ $initial ?: 'U' }}</span>
        </button>
        <div id="menuProfile" class="dropdown" role="menu" aria-labelledby="btnProfile" hidden>
          <a href="{{ $rtPerfil }}" role="menuitem">Mi perfil</a>
          <a href="{{ $rtSettings }}" role="menuitem">Configuración</a>
          <form action="{{ $rtLogout }}" method="POST" role="none">
            @csrf
            <button type="submit" role="menuitem">Cerrar sesión</button>
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

  .ico{ width:var(--ico); height:var(--ico); display:block; }

  .searchbar{
    display:flex; align-items:center; gap:8px;
    border:1px solid var(--bd,#e5e7eb); border-radius:999px;
    background: var(--chip, #f8fafc);
    padding:0 12px; height:40px; max-width:620px; width:100%;
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
  .btn.icon .ico{ color: var(--text,#0f172a); }

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

  .dropdown{
    position:absolute; right:0; top:calc(100% + 8px);
    background:var(--card,#fff); color:var(--text);
    border:1px solid var(--bd,#e5e7eb); border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,.12);
    display:flex; flex-direction:column; min-width:200px; padding:8px; z-index:60;
  }

  .dropdown[hidden]{ display:none !important; }

  html.theme-dark .dropdown{
    background:#0b1220; border-color:#2b2f36; color:#e5e7eb;
  }

  .dropdown a, .dropdown button{
    text-align:left; text-decoration:none; color:inherit;
    background:transparent; border:0; cursor:pointer;
    padding:10px 12px; border-radius:10px; font-weight:600;
  }
  .dropdown a:hover, .dropdown button:hover{
    background:rgba(0,0,0,.06);
  }
  html.theme-dark .dropdown a:hover,
  html.theme-dark .dropdown button:hover{
    background:rgba(255,255,255,.08);
  }

  @media (max-width: 900px){
    .acct-meta{ display:none; }
    .searchbar{ max-width:100%; }
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
