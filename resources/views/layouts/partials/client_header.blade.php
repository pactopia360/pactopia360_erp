{{-- resources/views/layouts/partials/client_header.blade.php
   Pactopia360 · Client Header · nuevo branding azul/blanco
   conserva dropdown, tema, badges, “Mi cuenta”, etc.
--}}
@php
  use Illuminate\Support\Facades\Route;

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
  $defaultGuard = (string) (config('auth.defaults.guard') ?? 'web');

  $u = null;

  try {
      $u = auth()->user();
  } catch (\Throwable $e) {
      $u = null;
  }

  if (!$u) {
      try {
          $u = auth($defaultGuard)->user();
      } catch (\Throwable $e) {
          $u = null;
      }
  }

  if (!$u) {
      try {
          $u = auth('cliente')->user();
      } catch (\Throwable $e) {
          $u = null;
      }
  }

  if (!$u) {
      try {
          $u = auth('web')->user();
      } catch (\Throwable $e) {
          $u = null;
      }
  }

  if (is_array($u)) {
      $u = (object) $u;
  }

  $c = $cuenta ?? ($u?->cuenta ?? null);

  if (is_array($c)) {
      $c = (object) $c;
  }

  $name  = $u?->nombre ?? $u?->name ?? $u?->email ?? 'Cuenta';
  $email = $u?->email ?? '';
  // Resumen unificado de cuenta
  // ✅ NO recalcular aquí. El layout ya manda $summary correcto.
  $summary = (isset($summary) && is_array($summary)) ? $summary : [];

  $summaryPlan = !empty($summary['plan'])
      ? strtoupper((string) $summary['plan'])
      : null;

  $summaryIsPro = array_key_exists('is_pro', $summary)
      ? (bool) $summary['is_pro']
      : null;

  $fallbackPlan = strtoupper((string)($c->plan_actual ?? $c->plan ?? 'FREE'));
  $planRaw      = $summaryPlan ?: $fallbackPlan;
  $planSlug     = strtolower(trim((string) $planRaw));

  $isProPlan = $summaryIsPro ?? in_array(
      $planSlug,
      ['pro', 'pro_mensual', 'pro_anual', 'premium', 'premium_mensual', 'premium_anual', 'empresa', 'business'],
      true
  );

  $plan = $planRaw;

  // Badge comercial compacto
  $planBadge = $isProPlan ? 'PRO' : (
      in_array($planSlug, ['free', 'gratis'], true) ? 'FREE' : $planRaw
  );

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
  $initialSource = (string) ($u?->nombre ?? $u?->name ?? $u?->email ?? 'U');
  $initial = strtoupper(mb_substr(trim($initialSource), 0, 1));

  // Nombre de cuenta mostrado
  $acctLabel = $summary['razon'] ?? $c?->razon_social ?? $u?->razon_social ?? $u?->nombre ?? $u?->name ?? $u?->email ?? 'Cuenta cliente';
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

  <div class="p360-head-actions">
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

    <a class="p360-icon-btn" href="{{ $rtAlerts }}" title="Notificaciones" aria-label="Notificaciones">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('bell') }}"/></svg>
      @if($notifCount > 0)
        <span class="p360-badge" aria-label="{{ $notifCount }} notificaciones">{{ $notifCount }}</span>
      @endif
    </a>

    <a class="p360-icon-btn" href="{{ $rtChat }}" title="Mensajes con soporte" aria-label="Mensajes con soporte">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $uHref('chat') }}"/></svg>
      @if($chatCount > 0)
        <span class="p360-badge" aria-label="{{ $chatCount }} mensajes">{{ $chatCount }}</span>
      @endif
    </a>

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