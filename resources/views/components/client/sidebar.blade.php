{{-- resources/views/components/client/sidebar.blade.php (v5.3.1 – Client Sidebar · label+desc + SOT módulos · FIX: bootstrapNoSotYet en closure + fallback oculto) --}}
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Str;

  $id        = $id        ?? 'sidebar';
  $isOpen    = (bool)($isOpen ?? false);
  $ariaLabel = $ariaLabel ?? 'Menú principal';
  $inst      = $inst      ?? Str::lower(Str::ulid());

  // =========================
  // Cuenta cliente (mysql_clientes) para flags runtime
  // =========================
  $user   = Auth::guard('web')->user();
  $cuenta = $cuenta ?? ($user->cuenta ?? null);

  // vault_active (db clientes): manda sobre el sidebar para mostrar/ocultar Bóveda
  $vaultActive = (int) data_get($cuenta, 'vault_active', 0) === 1;

  // =========================
  // Helper robusto: route safe
  // =========================
  $try = function(string $name, array $params = []) {
    try { return route($name, $params); } catch(\Throwable $e) {}
    return null;
  };

  // =========================
  // MÓDULOS desde sesión (SOT Admin)
  // =========================
  // SOT:
  //   p360.modules_state[key] = active|inactive|hidden|blocked
  //
  // fallback legacy:
  //   p360.modules[key] = bool
  $modsState  = session('p360.modules_state', []);
  $legacyMods = session('p360.modules', []);

  $hasSot    = is_array($modsState) && count($modsState) > 0;
  $hasLegacy = is_array($legacyMods) && count($legacyMods) > 0;

  // Si no hay SOT ni legacy, NO inventar activos: ocultar módulos (salvo Inicio/Config).
  $bootstrapNoSotYet = (!$hasSot && !$hasLegacy);

  $stateOf = function (string $key) use ($modsState, $legacyMods, $bootstrapNoSotYet): string {
    if (is_array($modsState) && array_key_exists($key, $modsState)) {
      $v = strtolower(trim((string) $modsState[$key]));
      return in_array($v, ['active','inactive','hidden','blocked'], true) ? $v : 'active';
    }

    if (is_array($legacyMods) && array_key_exists($key, $legacyMods)) {
      return ((bool) $legacyMods[$key]) ? 'active' : 'inactive';
    }

    // ✅ Bootstrap conservador: oculto hasta que la sesión esté poblada
    return $bootstrapNoSotYet ? 'hidden' : 'active';
  };

  $isVisible = function (string $key) use ($stateOf, $vaultActive): bool {
    // ✅ Bóveda: si no está activa a nivel cuenta (clientes), NO se muestra
    if ($key === 'boveda_fiscal' && !$vaultActive) return false;

    return $stateOf($key) !== 'hidden';
  };

  $canAccess = function (string $key) use ($stateOf, $vaultActive): bool {
    // ✅ Bóveda: además del SOT, requiere vault_active=1
    if ($key === 'boveda_fiscal') {
      return $vaultActive && ($stateOf($key) === 'active');
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

  // =========================
  // Rutas base
  // =========================
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

  // =========================
  // Rutas módulos
  // =========================
  $rtFact        = $resolveRoute(['cliente.facturacion.index','cliente.facturacion','cliente.facturacion.home'], null);
  $rtFactNew     = $resolveRoute(['cliente.facturacion.nuevo','cliente.facturacion.new','cliente.facturacion.create'], null);

  $rtSat         = $resolveRoute(['cliente.sat.index','cliente.sat','cliente.sat.home'], null);
  $rtDescargas   = $resolveRoute(['cliente.sat.descargas.index','cliente.sat.descargas','cliente.sat.descargas.home'], null);

  $rtVault       = $resolveRoute(['cliente.vault.index','cliente.vault','cliente.boveda.index','cliente.boveda'], null);

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

  // =========================
  // Activos (robustos)
  // =========================
  $isHome     = request()->routeIs('cliente.home') || request()->is('cliente/home');
  $isMiCuenta = request()->routeIs('cliente.mi_cuenta.*') || request()->is('cliente/mi-cuenta*');
  $isEstado   = request()->routeIs('cliente.estado_cuenta') || request()->is('cliente/estado-de-cuenta*');

  $isFact     = request()->routeIs('cliente.facturacion.*') || request()->is('cliente/facturacion*');
  $isSat      = request()->routeIs('cliente.sat.*') || request()->is('cliente/sat*');
  $isDown     = request()->routeIs('cliente.sat.descargas.*') || request()->is('cliente/sat/descargas*');
  $isVault    = request()->routeIs('cliente.vault.*') || request()->is('cliente/vault*') || request()->is('cliente/boveda*');

  $isCfg      = request()->is('cliente/config*') || request()->is('cliente/configuracion*') || request()->routeIs('cliente.mi_cuenta.*');

  // Inicia expanded (layout manda isOpen=true normalmente)
  $dataState = $isOpen ? 'expanded' : 'collapsed';

  // =========================
  // SVGs
  // =========================
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

  // ===== Descripciones UI =====
  $desc = [
    'Inicio' => 'Resumen y métricas',
    'Mi cuenta' => 'Perfil, plan y accesos',
    'Estado de cuenta' => 'Cargos, abonos y saldo',
    'Pagos' => 'Métodos y movimientos',
    'Facturas' => 'Descarga y gestión',

    'Facturación' => 'CFDI y administración',
    'Nuevo CFDI' => 'Crear comprobante',
    'SAT (Descarga)' => 'Detecta y procesa CFDI',
    'Descargas' => 'Historial SAT',
    'Bóveda Fiscal' => 'Archivos y resguardo',

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

  // Grupos
  $htmlCuenta = '';
  $htmlCuenta .= $renderItem('mi_cuenta','Mi cuenta',$rtMiCuenta,$isMiCuenta,$svgUser,false);
  $htmlCuenta .= $renderItem('estado_cuenta','Estado de cuenta',$rtEstadoCta,$isEstado,$svgBill,false);
  $htmlCuenta .= $renderItem('pagos','Pagos',$rtPagos,(request()->routeIs('cliente.mi_cuenta.pagos') || request()->is('cliente/mi-cuenta/pagos*')),$svgBag,false);
  $htmlCuenta .= $renderItem('facturas','Facturas',$rtFacturasMC,(request()->routeIs('cliente.mi_cuenta.facturas.*') || request()->is('cliente/mi-cuenta/facturas*')),$svgDoc,false);

  $htmlModulos = '';
  $htmlModulos .= $renderItem('facturacion','Facturación',$rtFact,$isFact,$svgBill,false);
  $htmlModulos .= $renderItem('facturacion','Nuevo CFDI',$rtFactNew,(request()->routeIs('cliente.facturacion.create') || request()->routeIs('cliente.facturacion.nuevo')),$svgPlus,false);

  $htmlModulos .= $renderItem('sat_descargas','SAT (Descarga)',$rtSat,($isSat && !$isDown),$svgSat,false);
  $htmlModulos .= $renderItem('sat_descargas','Descargas',$rtDescargas,$isDown,$svgDown,false);

  $htmlModulos .= $renderItem('boveda_fiscal','Bóveda Fiscal',$rtVault,$isVault,$svgBox,false);

  $htmlModulos .= $renderItem('crm','CRM',$rtCrm,(request()->routeIs('cliente.crm.*') || request()->is('cliente/crm*')),$svgUsers,false);
  $htmlModulos .= $renderItem('nomina','Nómina',$rtNomina,(request()->routeIs('cliente.nomina.*') || request()->is('cliente/nomina*')),$svgUsers,false);
  $htmlModulos .= $renderItem('pos','Punto de venta',$rtPos,(request()->routeIs('cliente.pos.*') || request()->is('cliente/pos*')),$svgStore,false);
  $htmlModulos .= $renderItem('inventario','Inventario',$rtInv,(request()->routeIs('cliente.inventario.*') || request()->is('cliente/inventario*')),$svgBox,false);
  $htmlModulos .= $renderItem('reportes','Reportes',$rtRep,(request()->routeIs('cliente.reportes.*') || request()->is('cliente/reportes*') || request()->is('cliente/dashboard*')),$svgChart,false);
  $htmlModulos .= $renderItem('integraciones','Integraciones',$rtInt,(request()->routeIs('cliente.integraciones.*') || request()->is('cliente/integraciones*') || request()->is('cliente/api*')),$svgLink,false);
  $htmlModulos .= $renderItem('alertas','Alertas',$rtAlert,(request()->routeIs('cliente.alertas.*') || request()->is('cliente/alertas*') || request()->is('cliente/notificaciones*')),$svgBell,false);
  $htmlModulos .= $renderItem('chat','Chat',$rtChat,(request()->routeIs('cliente.chat.*') || request()->is('cliente/chat*')),$svgChat,false);
  $htmlModulos .= $renderItem('marketplace','Marketplace',$rtMarket,(request()->routeIs('cliente.marketplace.*') || request()->is('cliente/marketplace*')),$svgBag,false);

  $htmlConfigAdv = $renderItem(
    'configuracion_avanzada',
    'Configuración avanzada',
    $rtCfgAdv,
    (request()->is('cliente/config*') || request()->is('cliente/configuracion*')),
    $svgGear,
    false
  );

  // Open accordions
  $openCuenta  = (bool) ($isMiCuenta || $isEstado || request()->is('cliente/mi-cuenta*') || request()->is('cliente/estado-de-cuenta*'));
  $openModulos = (bool) ($isFact || $isSat || $isDown || $isVault);
@endphp

@once
  @php
    $CLIENT_SB_CSS_ABS = public_path('assets/client/css/sidebar.css');
    $CLIENT_SB_CSS_URL = asset('assets/client/css/sidebar.css') . (is_file($CLIENT_SB_CSS_ABS) ? ('?v='.filemtime($CLIENT_SB_CSS_ABS)) : '');
  @endphp
  <link rel="stylesheet" href="{{ $CLIENT_SB_CSS_URL }}">
@endonce

<aside
  class="sidebar skin-brand-rail"
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
      data-sb-toggle="1"></button>
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
        {!! $htmlConfigAdv !!}

        <form method="POST" action="{{ $rtLogout }}" id="logoutForm-{{ $id }}-{{ $inst }}">
          @csrf
          <button type="submit" class="tip danger" title="Cerrar sesión">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M10 17v-2h4v2h-4zM4 12h10l-3-3l1.41-1.41L18.83 12l-6.42 6.41L11 17l3-3H4z"/>
            </svg>
            <span class="tx">
              <span class="lbl">Cerrar sesión</span>
            </span>
          </button>
        </form>
      </div>

    </nav>
  </div>
</aside>

@once
  <script src="{{ asset('assets/client/js/sidebar.js') }}" defer></script>
@endonce
