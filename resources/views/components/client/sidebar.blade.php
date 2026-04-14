{{-- resources/views/components/client/sidebar.blade.php
   Pactopia360 · Client Sidebar · branding nuevo azul/blanco
   conserva SOT módulos, permisos, estados, accordions y rutas
--}}
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Str;

  $id        = $id        ?? 'sidebar';
  $isOpen    = (bool)($isOpen ?? false);
  $ariaLabel = $ariaLabel ?? 'Menú principal';
  $inst      = $inst      ?? Str::lower(Str::ulid());

  $user   = Auth::guard('web')->user();
  $cuenta = $cuenta ?? ($user->cuenta ?? null);

  $vaultActive = (int) data_get($cuenta, 'vault_active', 0) === 1;

  $try = function(string $name, array $params = []) {
    try { return route($name, $params); } catch(\Throwable $e) {}
    return null;
  };

  $modsState  = session('p360.modules_state', []);
  $legacyMods = session('p360.modules', []);

  $hasSot    = is_array($modsState) && count($modsState) > 0;
  $hasLegacy = is_array($legacyMods) && count($legacyMods) > 0;

  $bootstrapNoSotYet = (!$hasSot && !$hasLegacy);

  $stateOf = function (string $key) use ($modsState, $legacyMods, $bootstrapNoSotYet): string {
    if (is_array($modsState) && array_key_exists($key, $modsState)) {
      $v = strtolower(trim((string) $modsState[$key]));
      return in_array($v, ['active','inactive','hidden','blocked'], true) ? $v : 'active';
    }

    if (is_array($legacyMods) && array_key_exists($key, $legacyMods)) {
      return ((bool) $legacyMods[$key]) ? 'active' : 'inactive';
    }

    return $bootstrapNoSotYet ? 'hidden' : 'active';
  };

  $isVisible = function (string $key) use ($stateOf, $vaultActive): bool {
    if ($key === 'boveda_fiscal' && !$vaultActive) return false;
    return $stateOf($key) !== 'hidden';
  };

  $canAccess = function (string $key) use ($stateOf, $vaultActive): bool {
      if ($key === 'boveda_fiscal') {
          if (!$vaultActive) return false;

          $st = $stateOf($key);
          if (in_array($st, ['blocked','hidden'], true)) {
              return false;
          }

          return true;
      }

      return $stateOf($key) === 'active';
  };

  $lockTitle = function (string $label, string $state): string {
    return match($state) {
      'blocked'  => $label.' (Módulo bloqueado por el administrador)',
      'inactive' => $label.' (Módulo deshabilitado por el administrador)',
      'hidden'   => $label.' (Módulo oculto)',
      default    => $label.' (No disponible)',
    };
  };

  $routeMissingTitle = fn(string $label) => $label.' (Ruta no configurada / no existe en routes/cliente.php)';

  $rtHome        = $try('cliente.home') ?: url('/cliente/home');
  $rtMiCuenta    = $try('cliente.mi_cuenta.index') ?: url('/cliente/mi-cuenta');
  $rtPagos       = $try('cliente.mi_cuenta.pagos') ?: null;
  $rtFacturasMC  = $try('cliente.mi_cuenta.facturas.index') ?: null;
  $rtEstadoCta   = $try('cliente.estado_cuenta') ?: url('/cliente/estado-de-cuenta');
  $rtLogout      = $try('cliente.logout') ?: url('/cliente/logout');

  $resolveRoute = function (array $routeTry, ?string $fallback = null) use ($try) {
    foreach ($routeTry as $rn) {
      if (!$rn) continue;
      $u = $try((string) $rn);
      if ($u) return $u;
    }
    return $fallback;
  };

  $rtFact        = $resolveRoute(['cliente.facturacion.index','cliente.facturacion','cliente.facturacion.home'], null);
  $rtFactNew     = $resolveRoute(['cliente.facturacion.nuevo','cliente.facturacion.new','cliente.facturacion.create'], null);

  $rtSatPortal   = $resolveRoute(['cliente.sat.index','cliente.sat','cliente.sat.home'], null);
  $rtSatCenter   = $resolveRoute(['cliente.sat.v2.index'], null);
  $rtSatRfcs     = $resolveRoute(['cliente.sat.rfcs.index'], null);
  $rtSatVault    = $resolveRoute(['cliente.sat.vault'], null);
  $rtSatReport   = $resolveRoute(['cliente.sat.report'], null);
  $rtSatCart     = $resolveRoute(['cliente.sat.cart.index'], null);

  $rtCrm         = $resolveRoute(['cliente.crm.index','cliente.crm'], null);
  $rtNomina      = $resolveRoute(['cliente.nomina.index','cliente.nomina'], null);
  $rtPos         = $resolveRoute(['cliente.pos.index','cliente.pos'], null);
  $rtInv         = $resolveRoute(['cliente.inventario.index','cliente.inventario'], null);
  $rtRep         = $resolveRoute(['cliente.reportes.index','cliente.reportes','cliente.dashboard.index','cliente.dashboard'], null);
  $rtInt         = $resolveRoute(['cliente.integraciones.index','cliente.integraciones','cliente.api.index','cliente.api'], null);
  $rtAlert       = $resolveRoute(['cliente.alertas.index','cliente.alertas','cliente.notificaciones.index','cliente.notificaciones'], null);
  $rtChat        = $resolveRoute(['cliente.chat.index','cliente.chat'], null);
  $rtMarket      = $resolveRoute(['cliente.marketplace.index','cliente.marketplace'], null);

  $rtCfgAdv      = $resolveRoute(['cliente.config.avanzada','cliente.configuracion.avanzada','cliente.config.index','cliente.configuracion.index'], null);

  $isHome     = request()->routeIs('cliente.home') || request()->is('cliente/home');
  $isMiCuenta = request()->routeIs('cliente.mi_cuenta.*') || request()->is('cliente/mi-cuenta*');
  $isEstado   = request()->routeIs('cliente.estado_cuenta') || request()->is('cliente/estado-de-cuenta*');

  $isFact        = request()->routeIs('cliente.facturacion.*') || request()->is('cliente/facturacion*');
  $isSatPortal   = request()->routeIs('cliente.sat.index') || request()->path() === 'cliente/sat';
  $isSatCenter   = request()->routeIs('cliente.sat.v2.*') || request()->is('cliente/sat/v2*');
  $isSatRfcs     = request()->routeIs('cliente.sat.rfcs.*') || request()->is('cliente/sat/rfcs*');
  $isSatVault    = request()->routeIs('cliente.sat.vault*') || request()->is('cliente/sat/vault*');
  $isSatReport   = request()->routeIs('cliente.sat.report*') || request()->is('cliente/sat/reporte*');
  $isSatCart     = request()->routeIs('cliente.sat.cart.*') || request()->is('cliente/sat/cart*');

  $isCfg      = request()->is('cliente/config*') || request()->is('cliente/configuracion*') || request()->routeIs('cliente.mi_cuenta.*');

  $dataState = $isOpen ? 'expanded' : 'collapsed';

  $svgHome = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.1 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>';
  $svgUser = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5Zm-8 9a8 8 0 1 1 16 0Z"/></svg>';
  $svgDoc  = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6 2h9l3 3v17H6zM8 8h8v2H8zm0 4h8v2H8zm0 4h8v2H8z"/></svg>';
  $svgBill = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 3h14l2 3v15H4zM6 7h8v2H6zm0 4h12v2H6zm0 4h12v2H6z"/></svg>';
  $svgPlus = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 5h2v6h6v2H11v6H9v-6H5v-2h4V5z"/></svg>';
  $svgSat  = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m21 7-6 6-4-4L3 17v2h18V7zM7 13a2 2 0 1 0-.001-4.001A2 2 0 0 0 7 13z"/></svg>';
  $svgDown = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3v10.586l3.293-3.293l1.414 1.414L12 17.414l-4.707-4.707l1.414-1.414L12 13.586V3zM5 19h14v2H5z"/></svg>';
  $svgBox  = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 8l-9-5-9 5v10l9 5 9-5V8Zm-9-2.8L18 8l-6 3.2L6 8l6-2.8Zm-7 4.5l6 3.2v7L5 17.2V9.7Zm14 0v7.5L13 20v-7l6-3.3Z"/></svg>';
  $svgChart= '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 19h16v2H4zM6 10h3v7H6zM11 6h3v11h-3zM16 12h3v5h-3z"/></svg>';
  $svgGear = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19.14 12.94a7.43 7.43 0 0 0 0-1.88l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.26 7.26 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 12.9 1h-3.8a.5.5 0 0 0-.49.42l-.36 2.54c-.58.24-1.12.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L1.71 7.48a.5.5 0 0 0 .12.64l2.03 1.58a7.43 7.43 0 0 0 0 1.88L1.83 14.5a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.72 1.63.94l.36 2.54a.5.5 0 0 0 .49.42h3.8a.5.5 0 0 0 .49-.42l.36-2.54c.58-.24 1.12-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.56ZM11 15a3 3 0 1 1 0-6a3 3 0 0 1 0 6Z"/></svg>';
  $svgBag  = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 7V6a5 5 0 0 1 10 0v1h3v15H4V7h3Zm2 0h6V6a3 3 0 0 0-6 0v1Z"/></svg>';
  $svgUsers= '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 11a4 4 0 1 0-4-4a4 4 0 0 0 4 4Zm-8 2a4 4 0 1 0-4-4a4 4 0 0 0 4 4Zm8 2c-3 0-6 1.5-6 4v1h12v-1c0-2.5-3-4-6-4ZM8 15c-.7 0-1.4.1-2 .3C4.3 16 3 17.2 3 19v1h6v-1c0-1.6.7-2.9 1.9-3.8A9.2 9.2 0 0 0 8 15Z"/></svg>';
  $svgStore= '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 4h16l2 6v2H2v-2l2-6Zm2 10h4v6H6v-6Zm6 0h6v6h-6v-6Z"/></svg>';
  $svgChat = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2ZM6 9h12v2H6V9Zm0-4h12v2H6V5Zm0 8h8v2H6v-2Z"/></svg>';
  $svgBell = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22a2 2 0 0 0 2-2H10a2 2 0 0 0 2 2Zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1l-2-2Z"/></svg>';
  $svgLink = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10.59 13.41a1.996 1.996 0 0 1 0-2.82l3.18-3.18a2 2 0 1 1 2.82 2.82l-1.06 1.06l1.41 1.41l1.06-1.06a4 4 0 0 0-5.66-5.66l-3.18 3.18a4 4 0 0 0 0 5.66l.7.7l1.41-1.41l-.68-.7ZM13.41 10.59a1.996 1.996 0 0 1 0 2.82l-3.18 3.18a2 2 0 1 1-2.82-2.82l1.06-1.06l-1.41-1.41l-1.06 1.06a4 4 0 0 0 5.66 5.66l3.18-3.18a4 4 0 0 0 0-5.66l-.7-.7l-1.41 1.41l.68.7Z"/></svg>';
  $svgLock = '<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16"><path fill="currentColor" d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v9a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-9a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5Zm-3 8V6a3 3 0 1 1 6 0v3H9Z"/></svg>';

  $desc = [
    'Inicio' => 'Resumen y métricas',
    'Mi cuenta' => 'Perfil, plan y accesos',
    'Estado de cuenta' => 'Cargos, abonos y saldo',
    'Pagos' => 'Métodos y movimientos',
    'Facturas' => 'Descarga y gestión',
    'Facturación' => 'CFDI y administración',
    'Nuevo CFDI' => 'Crear comprobante',
    'Portal SAT' => 'Vista general del módulo SAT',
    'Centro SAT' => 'Flujo operativo SAT',
    'RFC' => 'Administración de RFC SAT',
    'Carrito SAT' => 'Carrito y paquetes SAT',
    'CRM' => 'Clientes y oportunidades',
    'Nómina' => 'Recibos y timbrado',
    'Punto de venta' => 'Ventas y tickets',
    'Inventario' => 'Productos y existencias',
    'Reportes' => 'KPIs y analítica',
    'Integraciones' => 'API y conexiones',
    'Alertas' => 'Notificaciones',
    'Chat' => 'Soporte y mensajes',
    'Marketplace' => 'Addons y servicios',
    'Configuración' => 'Preferencias',
    'Configuración avanzada' => 'Opciones técnicas',
    'Cerrar sesión' => 'Salir de la plataforma',
  ];

  $renderItem = function (
    string $key,
    string $label,
    ?string $url,
    bool $active,
    string $icon,
    bool $always = false
  ) use (
    $stateOf, $isVisible, $canAccess, $lockTitle, $routeMissingTitle, $svgLock, $desc
  ) {
    if (!$always && !$isVisible($key)) return '';

    $hasUrl = is_string($url) && trim($url) !== '';
    $d = (string)($desc[$label] ?? '');

    $tx = '<span class="tx"><span class="lbl">'.e($label).'</span>'.($d!==''?'<span class="desc">'.e($d).'</span>':'').'</span>';

    if ($always) {
      if ($hasUrl) {
        return '<a href="'.e($url).'" class="tip '.($active?'active':'').'" '.($active?'aria-current="page"':'').' title="'.e($label).'">'.$icon.$tx.'</a>';
      }
      return '<a class="tip is-disabled" title="'.e($routeMissingTitle($label)).'">'.$icon.$tx.'<span class="lock" aria-hidden="true">'.$svgLock.'</span></a>';
    }

    $st = $stateOf($key);

    if (!$canAccess($key)) {
      return '<a class="tip is-disabled" title="'.e($lockTitle($label, $st)).'">'.$icon.$tx.'<span class="lock" aria-hidden="true">'.$svgLock.'</span></a>';
    }

    if (!$hasUrl) {
      return '<a class="tip is-disabled" title="'.e($routeMissingTitle($label)).'">'.$icon.$tx.'<span class="lock" aria-hidden="true">'.$svgLock.'</span></a>';
    }

    return '<a href="'.e($url).'" class="tip '.($active?'active':'').'" '.($active?'aria-current="page"':'').' title="'.e($label).'">'.$icon.$tx.'</a>';
  };

  $renderGroupIfAny = function (string $title, string $htmlItems, string $titleId, bool $accordion = true, bool $open = true) {
    $htmlItems = trim((string)$htmlItems);
    if ($htmlItems === '') return '';

    if ($accordion) {
      return '
        <details class="nav-acc" '.($open?'open':'').'>
          <summary class="nav-title" id="'.e($titleId).'"><span class="pill">'.e($title).'</span></summary>
          '.$htmlItems.'
        </details>
      ';
    }

    return '
      <div class="nav-group" aria-labelledby="'.e($titleId).'">
        <div class="nav-title" id="'.e($titleId).'"><span class="pill">'.e($title).'</span></div>
        '.$htmlItems.'
      </div>
    ';
  };

  $htmlCuenta = '';
  $htmlCuenta .= $renderItem('mi_cuenta','Mi cuenta',$rtMiCuenta,$isMiCuenta,$svgUser,false);
  $htmlCuenta .= $renderItem('estado_cuenta','Estado de cuenta',$rtEstadoCta,$isEstado,$svgBill,false);
  $htmlCuenta .= $renderItem('pagos','Pagos',$rtPagos,(request()->routeIs('cliente.mi_cuenta.pagos') || request()->is('cliente/mi-cuenta/pagos*')),$svgBag,false);
  $htmlCuenta .= $renderItem('facturas','Facturas',$rtFacturasMC,(request()->routeIs('cliente.mi_cuenta.facturas.*') || request()->is('cliente/mi-cuenta/facturas*')),$svgDoc,false);

  $htmlModulos = '';
  $htmlModulos .= $renderItem('facturacion','Facturación',$rtFact,$isFact,$svgBill,false);
  $htmlModulos .= $renderItem('facturacion','Nuevo CFDI',$rtFactNew,(request()->routeIs('cliente.facturacion.create') || request()->routeIs('cliente.facturacion.nuevo')),$svgPlus,false);
    $htmlModulos .= $renderItem(
    'sat_descargas',
    'Portal SAT',
    $rtSatPortal,
    ($isSatPortal || $isSatCenter || $isSatRfcs || $isSatCart),
    $svgSat,
    false
  );

  $htmlModulos .= $renderItem('sat_descargas','Centro SAT',$rtSatCenter,$isSatCenter,$svgSat,false);
  $htmlModulos .= $renderItem('sat_descargas','RFC',$rtSatRfcs,$isSatRfcs,$svgDoc,false);
  $htmlModulos .= $renderItem('sat_descargas','Carrito SAT',$rtSatCart,$isSatCart,$svgDown,false);
  $htmlModulos .= $renderItem('crm','CRM',$rtCrm,(request()->routeIs('cliente.crm.*') || request()->is('cliente/crm*')),$svgUsers,false);
  $htmlModulos .= $renderItem('nomina','Nómina',$rtNomina,(request()->routeIs('cliente.nomina.*') || request()->is('cliente/nomina*')),$svgUsers,false);
  $htmlModulos .= $renderItem('pos','Punto de venta',$rtPos,(request()->routeIs('cliente.pos.*') || request()->is('cliente/pos*')),$svgStore,false);
  $htmlModulos .= $renderItem('inventario','Inventario',$rtInv,(request()->routeIs('cliente.inventario.*') || request()->is('cliente/inventario*')),$svgBox,false);
  $htmlModulos .= $renderItem('reportes','Reportes',$rtRep,(request()->routeIs('cliente.reportes.*') || request()->is('cliente/reportes*') || request()->is('cliente/dashboard*')),$svgChart,false);
  $htmlModulos .= $renderItem('integraciones','Integraciones',$rtInt,(request()->routeIs('cliente.integraciones.*') || request()->is('cliente/integraciones*') || request()->is('cliente/api*')),$svgLink,false);
  $htmlModulos .= $renderItem('alertas','Alertas',$rtAlert,(request()->routeIs('cliente.alertas.*') || request()->is('cliente/alertas*') || request()->is('cliente/notificaciones*')),$svgBell,false);
  $htmlModulos .= $renderItem('chat','Chat',$rtChat,(request()->routeIs('cliente.chat.*') || request()->is('cliente/chat*')),$svgChat,false);
  $htmlModulos .= $renderItem('marketplace','Marketplace',$rtMarket,(request()->routeIs('cliente.marketplace.*') || request()->is('cliente/marketplace*')),$svgBag,false);

  $htmlConfigAdv = '';

  $openCuenta  = (bool) ($isMiCuenta || $isEstado || request()->is('cliente/mi-cuenta*') || request()->is('cliente/estado-de-cuenta*'));
  $openModulos = (bool) (
    $isFact ||
    $isSatPortal ||
    $isSatCenter ||
    $isSatRfcs ||
    $isSatCart
  );
@endphp

@once
  @php
    $CLIENT_SB_CSS_ABS = public_path('assets/client/css/sidebar.css');
    $CLIENT_SB_CSS_URL = asset('assets/client/css/sidebar.css') . (is_file($CLIENT_SB_CSS_ABS) ? ('?v='.filemtime($CLIENT_SB_CSS_ABS)) : '');
  @endphp
  <link rel="stylesheet" href="{{ $CLIENT_SB_CSS_URL }}">
@endonce

<aside
  class="sidebar skin-brand-rail p360-client-sidebar"
  id="{{ $id }}"
  aria-label="{{ $ariaLabel }}"
  data-state="{{ $dataState }}"
  data-component="p360-sidebar"
>

  <div class="sidebar-head">
    <strong class="sb-title">MENÚ</strong>
    <button
      class="sb-toggle"
      type="button"
      aria-label="Expandir/Colapsar"
      aria-expanded="{{ $isOpen ? 'true':'false' }}"
      title="Expandir/Colapsar (Ctrl+B)"
      data-sb-toggle="1"
    ></button>
  </div>

  <div class="sidebar-scroll" role="navigation" aria-label="Secciones">
    <nav class="nav">

      <div class="nav-group" aria-labelledby="nav-title-ini">
        <div class="nav-title" id="nav-title-ini"><span class="pill">Inicio</span></div>
        {!! $renderItem('__always__','Inicio',$rtHome,$isHome,$svgHome,true) !!}
      </div>

      {!! $renderGroupIfAny('Cuenta',  $htmlCuenta,  'nav-title-cta', true, $openCuenta) !!}
      {!! $renderGroupIfAny('Módulos', $htmlModulos, 'nav-title-mod', true, $openModulos) !!}

            <div class="nav-group" aria-labelledby="nav-title-cfg">
        <div class="nav-title" id="nav-title-cfg"><span class="pill">Configuración</span></div>

        {!! $renderItem('__always__','Configuración',$rtMiCuenta,$isCfg,$svgGear,true) !!}

        <form method="POST" action="{{ $rtLogout }}" id="logoutForm-{{ $id }}-{{ $inst }}">
          @csrf
          <button type="submit" class="tip danger" title="Cerrar sesión">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M10 17v-2h4v2h-4zM4 12h10l-3-3l1.41-1.41L18.83 12l-6.42 6.41L11 17l3-3H4z"/>
            </svg>
            <span class="tx">
              <span class="lbl">Cerrar sesión</span>
              <span class="desc">Salir de la plataforma</span>
            </span>
          </button>
        </form>
      </div>

    </nav>
  </div>
</aside>

<style>
  .p360-client-sidebar{
    background:rgba(255,255,255,.78) !important;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-right:1px solid rgba(37,99,235,.06) !important;
    box-shadow:6px 0 18px rgba(15,23,42,.035);
  }

  html.theme-dark .p360-client-sidebar{
    background:rgba(8,18,38,.78) !important;
    border-right-color:rgba(255,255,255,.06) !important;
    box-shadow:6px 0 18px rgba(0,0,0,.14);
  }

  .p360-client-sidebar .sidebar-head{
    padding-top:10px;
    padding-bottom:8px;
  }

  .p360-client-sidebar .sb-title{
    font-size:11px;
    letter-spacing:.12em;
    font-weight:800;
    color:var(--muted,#64748b);
  }

  .p360-client-sidebar .sb-toggle{
    border-radius:10px;
    background:rgba(255,255,255,.76);
    border:1px solid rgba(37,99,235,.08);
    box-shadow:none;
  }

  html.theme-dark .p360-client-sidebar .sb-toggle{
    background:rgba(255,255,255,.05);
    border-color:rgba(255,255,255,.07);
    box-shadow:none;
  }

  .p360-client-sidebar .nav-title .pill{
    background:rgba(37,99,235,.07);
    color:#2563eb;
    border:1px solid rgba(37,99,235,.08);
    border-radius:999px;
    font-weight:800;
    letter-spacing:.03em;
  }

  html.theme-dark .p360-client-sidebar .nav-title .pill{
    background:rgba(96,165,250,.10);
    color:#dbeafe;
    border-color:rgba(96,165,250,.10);
  }

  .p360-client-sidebar .tip{
    border-radius:14px;
    transition:background .16s ease, border-color .16s ease, transform .16s ease;
    border:1px solid transparent;
    box-shadow:none;
  }

  .p360-client-sidebar .tip:hover{
    transform:none;
    background:rgba(37,99,235,.045);
    border-color:rgba(37,99,235,.06);
  }

  html.theme-dark .p360-client-sidebar .tip:hover{
    background:rgba(255,255,255,.045);
    border-color:rgba(255,255,255,.06);
  }

  .p360-client-sidebar .tip.active{
    background:rgba(37,99,235,.10);
    border-color:rgba(37,99,235,.10);
    box-shadow:none;
  }

  html.theme-dark .p360-client-sidebar .tip.active{
    background:rgba(96,165,250,.12);
    border-color:rgba(96,165,250,.10);
    box-shadow:none;
  }

  .p360-client-sidebar .tip .ico{
    color:#2563eb;
  }

  html.theme-dark .p360-client-sidebar .tip .ico{
    color:#93c5fd;
  }

  .p360-client-sidebar .tip .lbl{
    font-weight:800;
    color:var(--ink,#0f172a);
  }

  html.theme-dark .p360-client-sidebar .tip .lbl{
    color:#e6efff;
  }

  .p360-client-sidebar .tip .desc{
    margin-top:2px;
    font-size:11px;
    color:var(--muted,#64748b);
  }

  html.theme-dark .p360-client-sidebar .tip .desc{
    color:#94a3b8;
  }

  .p360-client-sidebar .tip.is-disabled{
    opacity:.58;
    cursor:not-allowed;
  }

  .p360-client-sidebar .tip.danger{
    color:#b91c1c;
  }

  .p360-client-sidebar .tip.danger .ico{
    color:#dc2626;
  }

  html.theme-dark .p360-client-sidebar .tip.danger{
    color:#fca5a5;
  }

  html.theme-dark .p360-client-sidebar .tip.danger .ico{
    color:#fca5a5;
  }

  .p360-client-sidebar .tip.danger:hover{
    background:rgba(239,68,68,.08);
    border-color:rgba(239,68,68,.08);
  }

  .p360-client-sidebar .lock{
    color:#94a3b8;
  }

  html.theme-dark .p360-client-sidebar .lock{
    color:#9fb0ca;
  }

    /* ===== FIX Pactopia: quitar líneas rosas/rojas heredadas ===== */
  .p360-client-sidebar,
  .p360-client-sidebar::before,
  .p360-client-sidebar::after,
  .p360-client-sidebar .sidebar-head,
  .p360-client-sidebar .sidebar-scroll,
  .p360-client-sidebar .nav,
  .p360-client-sidebar .nav-group,
  .p360-client-sidebar .nav-acc,
  .p360-client-sidebar .nav-acc::before,
  .p360-client-sidebar .nav-acc::after{
    border-right-color: transparent !important;
    border-left-color: transparent !important;
    box-shadow: none;
  }

  /* línea vertical del costado derecho */
  .p360-client-sidebar{
    border-right: 1px solid rgba(37,99,235,.06) !important;
  }

  html.theme-dark .p360-client-sidebar{
    border-right: 1px solid rgba(255,255,255,.06) !important;
  }

  /* item activo sin barra roja/rosa lateral */
  .p360-client-sidebar .tip.active,
  .p360-client-sidebar .tip[aria-current="page"],
  .p360-client-sidebar .tip.active::before,
  .p360-client-sidebar .tip.active::after,
  .p360-client-sidebar .tip[aria-current="page"]::before,
  .p360-client-sidebar .tip[aria-current="page"]::after{
    border-left-color: transparent !important;
    border-right-color: transparent !important;
    box-shadow: none !important;
  }

  .p360-client-sidebar .tip.active::before,
  .p360-client-sidebar .tip.active::after,
  .p360-client-sidebar .tip[aria-current="page"]::before,
  .p360-client-sidebar .tip[aria-current="page"]::after{
    content: none !important;
    display: none !important;
    background: transparent !important;
  }

  /* summary/details del acordeón sin acentos rojos heredados */
  .p360-client-sidebar details,
  .p360-client-sidebar summary,
  .p360-client-sidebar .nav-title,
  .p360-client-sidebar .pill{
    border-left-color: transparent !important;
    border-right-color: transparent !important;
    outline-color: transparent !important;
  }

</style>

@once
  <script src="{{ asset('assets/client/js/sidebar.js') }}" defer></script>
@endonce