{{-- resources/views/admin/billing/accounts/show.blade.php
     P360 Admin Billing · Account Admin v6.2
     FIX REAL (SOT): usa meta.modules_state (active|inactive|hidden|blocked)
     - UI completa: 4 estados por módulo + acciones masivas + búsqueda + contadores
     - Catálogo UI base (incluye Cuenta + Módulos)
     - Lee: $modules_state (controller) -> fallback a meta.modules_state -> fallback legacy meta.modules
     - Guarda: modules_state[key]=...
     FIX CRÍTICO:
     - Route HUB correcto: admin.billing.statements_hub.index (no admin.billing.statements_hub)
--}}

@extends(request()->boolean('modal') ? 'layouts.admin_modal' : 'layouts.admin')

@section('title', 'Administrar cuenta #'.$account->id)

@php
  $isModal = request()->boolean('modal');

  $name  = $account->razon_social ?: ($account->name ?: '—');
  $rfc   = $account->rfc ?: '—';
  $email = $account->email ?: '—';

  $pk    = (string)($price_key ?? data_get($meta ?? [], 'billing.price_key', ''));
  $cycle = (string)($billing_cycle ?? data_get($meta ?? [], 'billing.billing_cycle', ''));

  $base = (int)($base_amount_mxn ?? data_get($meta ?? [], 'billing.amount_mxn', 0));

  // Override: nuevo + legacy
  $overrideLegacy = data_get($meta ?? [], 'billing.override_amount_mxn', null);
  $overrideNew    = data_get($meta ?? [], 'billing.override.amount_mxn', null);

  $override = null;
  if (isset($override_amount_mxn)) {
    $override = is_null($override_amount_mxn) ? null : (int)$override_amount_mxn;
  } else {
    $raw = $overrideNew ?? $overrideLegacy;
    $override = is_null($raw) ? null : (is_numeric($raw) ? (int)$raw : null);
  }

  $isCustom = ($override !== null);
  $current = (int)($current_amount_mxn ?? ($isCustom ? $override : $base));

  $override_effective  = (string)($override_effective ?? data_get($meta ?? [], 'billing.override.effective', data_get($meta ?? [], 'billing.override_effective', 'next')));
  $override_updated_at = (string)($override_updated_at ?? data_get($meta ?? [], 'billing.override.updated_at', data_get($meta ?? [], 'billing.override_updated_at', '')));

  $stripe_price_id = (string)($stripe_price_id ?? data_get($meta ?? [], 'billing.stripe_price_id', ''));

  $periodNow = (string)($periodNow ?? now()->format('Y-m'));
  $catalog = is_array($catalog ?? null) ? $catalog : [];

  $fmt = function($n){
    $n = (int)$n;
    return '$'.number_format($n, 0, '.', ',').' MXN';
  };

  $tab = (string) request('tab', 'overview');
  $tab = in_array($tab, ['overview','billing','modules'], true) ? $tab : 'overview';

  $backUrl  = route('admin.billing.accounts.index');

  // ✅ FIX: el HUB correcto es admin.billing.statements_hub.index
  if (\Illuminate\Support\Facades\Route::has('admin.billing.statements.show')) {
    $stateUrl = route('admin.billing.statements.show', [$account->id, $periodNow]);
  } elseif (\Illuminate\Support\Facades\Route::has('admin.billing.statements_hub.index')) {
    $stateUrl = route('admin.billing.statements_hub.index', ['period'=>$periodNow, 'q'=>$account->id]);
  } else {
    // fallback ultra-safe (evita 500 -> modal en blanco)
    $stateUrl = $backUrl;
  }
  if ($isModal) $stateUrl .= (str_contains($stateUrl, '?') ? '&' : '?') . 'modal=1';

  $tabUrl = function(string $t) use ($account, $isModal){
    $u = route('admin.billing.accounts.show', $account->id);
    $q = ['tab'=>$t];
    if ($isModal) $q['modal'] = '1';
    return $u.'?'.http_build_query($q);
  };

  $idRaw   = (string)($account->id ?? '');
  $idShort = $idRaw !== '' ? (mb_strlen($idRaw) > 12 ? ('…'.mb_substr($idRaw, -12)) : $idRaw) : '—';

  $isBlocked = (bool)($isBlocked ?? ((int)($account->is_blocked ?? 0) === 1));

  // =========================
  // Estados soportados (SOT)
  // =========================
  $ST_ACTIVE   = 'active';
  $ST_INACTIVE = 'inactive';
  $ST_HIDDEN   = 'hidden';
  $ST_BLOCKED  = 'blocked';

  $normState = function($s) use ($ST_ACTIVE,$ST_INACTIVE,$ST_HIDDEN,$ST_BLOCKED){
    $s = strtolower(trim((string)$s));
    return in_array($s, [$ST_ACTIVE,$ST_INACTIVE,$ST_HIDDEN,$ST_BLOCKED], true) ? $s : $ST_ACTIVE;
  };

  /**
   * =========================================================
   * ✅ CATÁLOGO BASE (UI SOT)
   * - Incluye navegación de cuenta (lo que te faltaba)
   * =========================================================
   */
  $baseModulesCatalog = [
    // Cuenta (navegación en cliente)
    'mi_cuenta' => [
      'label' => 'Mi cuenta',
      'desc'  => 'Pantalla de cuenta, perfil, configuración y accesos.',
      'group' => 'Cuenta',
    ],
    'estado_cuenta' => [
      'label' => 'Estado de cuenta',
      'desc'  => 'Estados de cuenta, periodos y cargos.',
      'group' => 'Cuenta',
    ],
    'pagos' => [
      'label' => 'Pagos',
      'desc'  => 'Historial de pagos, métodos y confirmaciones.',
      'group' => 'Cuenta',
    ],
    'facturas' => [
      'label' => 'Facturas',
      'desc'  => 'Facturas emitidas por la plataforma (billing).',
      'group' => 'Cuenta',
    ],

    // Core / Operación
    'facturacion' => [
      'label' => 'Facturación',
      'desc'  => 'Emisión/gestión de CFDI.',
      'group' => 'Operación',
    ],
    'sat_descargas' => [
      'label' => 'SAT Descargas Masivas',
      'desc'  => 'Descarga masiva CFDI (SAT) y automatizaciones.',
      'group' => 'Fiscal',
    ],
    'boveda_fiscal' => [
      'label' => 'Bóveda Fiscal',
      'desc'  => 'Almacenamiento/consulta de XML CFDI y reportes.',
      'group' => 'Fiscal',
    ],

    // Comercial / Relación
    'crm' => [
      'label' => 'CRM',
      'desc'  => 'Prospectos, pipeline, actividades y seguimiento.',
      'group' => 'Comercial',
    ],
    'marketplace' => [
      'label' => 'Marketplace',
      'desc'  => 'Catálogo/servicios y complementos.',
      'group' => 'Comercial',
    ],

    // RRHH / Nómina
    'nomina' => [
      'label' => 'Nómina',
      'desc'  => 'Gestión nómina (CFDI), empleados y timbrado.',
      'group' => 'RRHH',
    ],

    // Operación / Venta
    'pos' => [
      'label' => 'Punto de venta',
      'desc'  => 'Caja, tickets, productos y ventas.',
      'group' => 'Operación',
    ],
    'inventario' => [
      'label' => 'Inventario',
      'desc'  => 'Existencias, movimientos, kardex y almacenes.',
      'group' => 'Operación',
    ],

    // Sistema
    'reportes' => [
      'label' => 'Reportes',
      'desc'  => 'KPIs, dashboards y exportaciones.',
      'group' => 'Sistema',
    ],
    'integraciones' => [
      'label' => 'Integraciones',
      'desc'  => 'Conectores externos, API, webhooks.',
      'group' => 'Sistema',
    ],
    'alertas' => [
      'label' => 'Alertas',
      'desc'  => 'Alertas operativas y notificaciones.',
      'group' => 'Sistema',
    ],
    'chat' => [
      'label' => 'Chat',
      'desc'  => 'Mensajería/soporte interno.',
      'group' => 'Sistema',
    ],
    'configuracion_avanzada' => [
      'label' => 'Configuración avanzada',
      'desc'  => 'Preferencias avanzadas por cuenta.',
      'group' => 'Sistema',
    ],
  ];

  /**
   * =========================================================
   * ✅ Normaliza $modules_catalog si llega del controller.
   * =========================================================
   */
  $modules_catalog_in = is_array($modules_catalog ?? null) ? $modules_catalog : [];
  $normalizedIncoming = [];

  foreach ($modules_catalog_in as $k => $v) {
    $k = (string)$k;
    if ($k === '') continue;

    if (is_array($v)) {
      $normalizedIncoming[$k] = [
        'label' => (string)($v['label'] ?? $v['name'] ?? $k),
        'desc'  => (string)($v['desc'] ?? $v['description'] ?? ''),
        'group' => (string)($v['group'] ?? 'Otros'),
      ];
    } else {
      $normalizedIncoming[$k] = [
        'label' => (string)$v,
        'desc'  => '',
        'group' => 'Otros',
      ];
    }
  }

  // Merge: base + incoming
  $modulesCatalog = $baseModulesCatalog;
  foreach ($normalizedIncoming as $k => $def) {
    $modulesCatalog[$k] = array_merge($modulesCatalog[$k] ?? [], $def);
  }

  uasort($modulesCatalog, function($a, $b){
    $ga = (string)($a['group'] ?? '');
    $gb = (string)($b['group'] ?? '');
    if ($ga !== $gb) return strcasecmp($ga, $gb);
    return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
  });

  /**
   * =========================================================
   * ✅ Estado actual (modules_state)
   * - 1) $modules_state desde controller
   * - 2) meta.modules_state
   * - 3) legacy meta.modules bool -> active/inactive
   * =========================================================
   */
  $modules_state = is_array($modules_state ?? null) ? $modules_state : [];

  if (!$modules_state) {
    $ms = data_get($meta ?? [], 'modules_state', null);
    if (!is_array($ms)) $ms = data_get($meta ?? [], 'meta.modules_state', null);
    if (is_array($ms)) $modules_state = $ms;
  }

  // legacy bool fallback
  if (!$modules_state) {
    $legacy = data_get($meta ?? [], 'modules', null);
    if (!is_array($legacy)) $legacy = data_get($meta ?? [], 'meta.modules', null);
    $tmp = [];
    if (is_array($legacy)) {
      foreach ($legacy as $k => $v) {
        $k = (string)$k; if ($k==='') continue;
        $tmp[$k] = ((bool)$v) ? $ST_ACTIVE : $ST_INACTIVE;
      }
    }
    $modules_state = $tmp;
  }

  // Completar faltantes con default active
  $modulesState = [];
  foreach ($modulesCatalog as $k => $_def) {
    $modulesState[$k] = $normState($modules_state[$k] ?? $ST_ACTIVE);
  }

  // Agrupar
  $grouped = [];
  foreach ($modulesCatalog as $k => $def) {
    $g = (string)($def['group'] ?? 'Otros');
    $grouped[$g] = $grouped[$g] ?? [];
    $grouped[$g][$k] = $def;
  }
  ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

  $totalModules = count($modulesCatalog);

  $counts = [
    $ST_ACTIVE   => 0,
    $ST_INACTIVE => 0,
    $ST_HIDDEN   => 0,
    $ST_BLOCKED  => 0,
  ];
  foreach ($modulesState as $st) {
    $counts[$st] = ($counts[$st] ?? 0) + 1;
  }
@endphp

@push('styles')
<style>
  :root{
    --ink:#0f172a;
    --mut:#64748b;
    --line:rgba(15,23,42,.10);
    --line2:rgba(15,23,42,.08);
    --bg:#f6f7fb;
    --card:#ffffff;
    --shadow: 0 14px 40px rgba(2,6,23,.10);
    --soft:#f8fafc;
    --soft2:#f1f5f9;
    --btn:#0f172a;
    --vio:#5b21b6;
    --vioBg:#ede9fe;
    --vioBd:#ddd6fe;
    --r:16px;
  }

  .p360{
    width:100%;
    min-height:100vh;
    background:var(--bg);
    padding: {{ $isModal ? '14px 18px' : '18px 22px' }};
    box-sizing:border-box;
  }

  .shell{
    width:100%;
    max-width: {{ $isModal ? 'none' : '1500px' }};
    margin: 0 auto;
  }

  .top{
    position: sticky;
    top:0;
    z-index:30;
    background: rgba(246,247,251,.92);
    backdrop-filter: blur(10px) saturate(140%);
    border-bottom:1px solid var(--line2);
    padding-bottom:10px;
    margin-bottom:14px;
  }

  .topbar{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    padding-top:6px;
  }

  .ttl h1{
    margin:0;
    font-size:18px;
    font-weight:950;
    letter-spacing:-.02em;
    color:var(--ink);
    line-height:1.15;
  }
  .sub{
    margin-top:4px;
    font-size:12px;
    font-weight:850;
    color:var(--mut);
    max-width:min(100%, 1200px);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .rhs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
    align-items:center;
  }

  .btn{
    appearance:none;
    border:1px solid rgba(15,23,42,.12);
    background:#fff;
    color:var(--ink);
    font-weight:950;
    border-radius:12px;
    padding:10px 12px;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:8px;
    white-space:nowrap;
  }
  .btn.primary{
    background:var(--btn);
    color:#fff;
    border-color: rgba(15,23,42,.22);
  }
  .btn.soft{ background:var(--soft); }

  .pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:950;
    border:1px solid;
    white-space:nowrap;
  }
  .pill.ok{ background:#dcfce7; border-color:#bbf7d0; color:#166534; }
  .pill.bad{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
  .pill.info{ background:#e0f2fe; border-color:#bae6fd; color:#075985; }
  .pill.warn{ background:#fef3c7; border-color:#fde68a; color:#92400e; }
  .pill.custom{ background:var(--vioBg); border-color:var(--vioBd); color:var(--vio); }
  .pill.neu{ background:#f1f5f9; border-color:#e2e8f0; color:#0f172a; }

  .tabs{
    margin-top:10px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .tab{
    padding:9px 12px;
    border-radius:12px;
    text-decoration:none;
    font-weight:950;
    font-size:12px;
    color:var(--ink);
    border:1px solid var(--line);
    background:#fff;
  }
  .tab.active{
    background:var(--btn);
    color:#fff;
    border-color: rgba(15,23,42,.22);
  }

  .grid{
    display:grid;
    grid-template-columns: 1.15fr .85fr;
    gap:14px;
    align-items:start;
  }

  .card{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:16px;
    box-shadow:var(--shadow);
    overflow:hidden;
  }

  .hd{
    padding:12px 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
    border-bottom:1px solid var(--line2);
    background:#fff;
  }
  .hd .h{ font-weight:950; color:var(--ink); line-height:1.1; }
  .hd .s{ margin-top:2px; font-weight:850; font-size:12px; color:var(--mut); }

  .bd{ padding:12px; }

  .kpis{
    display:grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap:10px;
  }
  .kpi{
    border:1px solid var(--line2);
    border-radius:14px;
    padding:10px 10px;
    background:var(--soft);
  }
  .kpi .k{ font-size:11px; font-weight:900; color:var(--mut); }
  .kpi .v{ margin-top:3px; font-size:14px; font-weight:950; color:var(--ink); }

  .row2{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
  .form{ display:grid; gap:10px; }
  .lbl{ font-size:11px; font-weight:950; color:var(--mut); margin-bottom:6px; }
  .in,.sel{
    width:100%;
    border:1px solid var(--line);
    border-radius:12px;
    padding:10px 10px;
    background:#fff;
    color:var(--ink);
    font-weight:900;
    outline:0;
  }
  .help{ margin-top:6px; font-size:12px; color:var(--mut); font-weight:800; }
  .line{ height:1px; background:var(--line2); margin:10px 0; }

  .note{
    border:1px solid var(--line);
    border-radius:14px;
    padding:10px 10px;
    background:var(--soft);
    font-weight:850;
    color:var(--ink);
  }
  .note small{ display:block; margin-top:4px; color:var(--mut); font-weight:800; }

  .stack{ display:grid; gap:12px; }

  /* Modules */
  .modsHead{
    display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;
    margin-bottom:10px;
  }
  .modsHead .lhs{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .modsHead .rhs{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }

  .mods{ display:grid; gap:10px; }
  .groupTitle{
    margin-top:2px;
    display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
    padding:10px 10px;
    border:1px solid var(--line2);
    border-radius:14px;
    background:var(--soft);
  }
  .groupTitle .g{ font-weight:950; color:var(--ink); }
  .groupTitle .c{ font-weight:850; color:var(--mut); font-size:12px; }

  .mod{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    border:1px solid var(--line2);
    border-radius:14px;
    padding:10px 10px;
    background:#fff;
  }
  .mod.is-hidden{ display:none; }

  .mod .n{ font-weight:950; color:var(--ink); }
  .mod .d{ font-weight:800; color:var(--mut); font-size:12px; margin-top:2px; }

  .statePill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    padding:6px 10px;
    font-size:12px;
    font-weight:950;
    white-space:nowrap;
    background:#fff;
    color:#0f172a;
  }
  .statePill[data-st="active"]{ background:#dcfce7; border-color:#bbf7d0; color:#166534; }
  .statePill[data-st="inactive"]{ background:#fef3c7; border-color:#fde68a; color:#92400e; }
  .statePill[data-st="hidden"]{ background:#e2e8f0; border-color:#cbd5e1; color:#0f172a; }
  .statePill[data-st="blocked"]{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }

  .mono{
    font: 12px/1 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace;
    font-weight:900;
  }

  .flash{
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
    padding:10px 12px;
    box-shadow: 0 10px 26px rgba(2,6,23,.06);
    margin-bottom:12px;
    font-weight:850;
    color:var(--ink);
  }
  .flash.ok{ background:#ecfdf5; border-color:#bbf7d0; color:#065f46; }
  .flash.bad{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }
  .flash .mini{ margin-top:4px; font-size:12px; font-weight:800; color:var(--mut); }

  @media (max-width: 1100px){
    .grid{ grid-template-columns: 1fr; }
    .kpis{ grid-template-columns: 1fr; }
    .row2{ grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
<div class="p360">
  <div class="shell">

    @if(session('ok'))
      <div class="flash ok">
        {{ session('ok') }}
        <div class="mini">Cambios guardados correctamente.</div>
      </div>
    @endif

    @if(session('success'))
      <div class="flash ok">
        {{ session('success') }}
        <div class="mini">Cambios guardados correctamente.</div>
      </div>
    @endif

    @if(session('error'))
      <div class="flash bad">
        {{ session('error') }}
        <div class="mini">Revisa tu configuración o logs.</div>
      </div>
    @endif

    @if($errors && $errors->any())
      <div class="flash bad">
        Se detectaron errores de validación.
        <div class="mini">
          <ul style="margin:6px 0 0 18px;">
            @foreach($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    @endif

    <div class="top">
      <div class="topbar">
        <div class="ttl">
          <h1>Administrar · #{{ $idShort }}</h1>
          <div class="sub">{{ $name }} · RFC {{ $rfc }} · {{ $email }}</div>
        </div>

        <div class="rhs">
          @if(!$isModal)
            <a class="btn" href="{{ $backUrl }}">Volver</a>
          @endif
          <a class="btn primary" href="{{ $stateUrl }}">Ver estado ({{ $periodNow }})</a>

          @if($isCustom)
            <span class="pill custom">PERSONALIZADO</span>
          @endif

          @if($isBlocked)
            <span class="pill bad">BLOQUEADO</span>
          @else
            <span class="pill ok">ACTIVO</span>
          @endif
        </div>
      </div>

      <div class="tabs">
        <a class="tab {{ $tab==='overview'?'active':'' }}" href="{{ $tabUrl('overview') }}">Resumen</a>
        <a class="tab {{ $tab==='billing'?'active':'' }}" href="{{ $tabUrl('billing') }}">Licencia / Precio</a>
        <a class="tab {{ $tab==='modules'?'active':'' }}" href="{{ $tabUrl('modules') }}">Módulos</a>
      </div>
    </div>

    {{-- OVERVIEW --}}
    @if($tab === 'overview')
      <div class="card">
        <div class="hd">
          <div>
            <div class="h">Resumen de facturación</div>
            <div class="s">Plan asignado, cobro actual y configuración clave.</div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            @if($pk !== '')
              <span class="pill info">price_key: {{ $pk }}</span>
            @else
              <span class="pill warn">SIN LICENCIA</span>
            @endif
            @if($isCustom)
              <span class="pill custom">PERSONALIZADO: {{ $fmt($override) }}</span>
            @endif
          </div>
        </div>

        <div class="bd">
          <div class="kpis">
            <div class="kpi"><div class="k">Precio base</div><div class="v">{{ $fmt($base) }}</div></div>
            <div class="kpi"><div class="k">Paga actualmente</div><div class="v">{{ $fmt($current) }}</div></div>
            <div class="kpi"><div class="k">Ciclo</div><div class="v">{{ $cycle ?: '—' }}</div></div>
          </div>

          <div class="line"></div>

          <div class="note">
            ID cuenta: <span class="mono">{{ $account->id }}</span>
            <small>Stripe Price ID: <span class="mono">{{ $stripe_price_id ?: '—' }}</span></small>

            @if($isCustom)
              <small>
                Override: <span class="mono">{{ $fmt($override) }}</span> · aplica:
                <b>{{ $override_effective === 'now' ? 'inmediato' : 'próximo ciclo' }}</b>
                @if($override_updated_at) · actualizado: {{ $override_updated_at }}@endif
              </small>
            @endif
          </div>
        </div>
      </div>
    @endif

    {{-- BILLING --}}
    @if($tab === 'billing')
      <div class="grid">

        <div class="card">
          <div class="hd">
            <div>
              <div class="h">Licencia / Precio</div>
              <div class="s">Asigna price_key, valida base y controla override.</div>
            </div>
            <span class="pill info">meta.billing.*</span>
          </div>

          <div class="bd">
            <form class="form" method="POST" action="{{ route('admin.billing.accounts.license', $account->id) }}">
              @csrf

              <div class="row2">
                <div>
                  <div class="lbl">Price (price_key)</div>
                  <select class="sel" name="price_key" id="price_key">
                    @foreach($catalog as $key => $p)
                      @php
                        $label = (string)($p['label'] ?? $key);
                        $cy    = (string)($p['billing_cycle'] ?? '');
                        $amt   = (int)($p['amount_mxn'] ?? 0);
                        $suffix = $cy ? " · {$cy} · {$fmt($amt)}" : " · {$fmt($amt)}";
                      @endphp
                      <option value="{{ $key }}" @selected($key === $pk)>{{ $label }} ({{ $key }}){{ $suffix }}</option>
                    @endforeach
                  </select>
                  <div class="help">Guarda: price_key, billing_cycle, amount_mxn, stripe_price_id.</div>
                </div>

                <div>
                  <div class="lbl">Vista previa (base)</div>
                  <div class="note" id="previewBase">
                    <span data-val>{{ $fmt($base) }}</span>
                    <small>Costo base por licencia (sin override).</small>
                  </div>
                </div>
              </div>

              <button class="btn primary" type="submit">Guardar licencia</button>
            </form>

            <div class="line"></div>

            <form class="form" method="POST" action="{{ route('admin.billing.accounts.override', $account->id) }}">
              @csrf

              <div class="row2">
                <div>
                  <div class="lbl">Override mensual</div>
                  <select class="sel" name="override_mode" id="override_mode">
                    <option value="none" @selected($override === null)>Usar precio asignado</option>
                    <option value="set"  @selected($override !== null)>Definir costo mensual personalizado</option>
                  </select>
                  <div class="help">Tu backend debe respetar override al cobrar.</div>
                </div>

                <div>
                  <div class="lbl">Monto mensual MXN</div>
                  <input class="in" type="number" min="0" step="1" name="override_amount_mxn" id="override_amount_mxn"
                         value="{{ $override !== null ? $override : '' }}" placeholder="Ej. 799">
                  <div class="help" id="overrideHint">
                    @if($override !== null)
                      Guardado: {{ $fmt($override) }} · aplica: <b>{{ $override_effective === 'now' ? 'inmediato' : 'próximo ciclo' }}</b>
                      @if($override_updated_at) · actualizado: {{ $override_updated_at }} @endif
                    @else
                      No hay override activo.
                    @endif
                  </div>
                </div>
              </div>

              <div class="row2">
                <div>
                  <div class="lbl">Aplicación</div>
                  <select class="sel" name="override_effective" id="override_effective">
                    <option value="next" @selected($override_effective === 'next')>Próximo ciclo</option>
                    <option value="now"  @selected($override_effective === 'now')>Inmediato</option>
                  </select>
                  <div class="help">“Inmediato” requiere que el flujo de cobro use override desde hoy.</div>
                </div>

                <div>
                  <div class="lbl">Vista previa (paga)</div>
                  <div class="note" id="previewPay">
                    <span data-val>{{ $fmt($current) }}</span>
                    <small>Costo efectivo (override si aplica).</small>
                  </div>
                </div>
              </div>

              <button class="btn primary" type="submit">Guardar override</button>
            </form>
          </div>
        </div>

        <div class="stack">
          <div class="card">
            <div class="hd">
              <div>
                <div class="h">Contexto</div>
                <div class="s">Datos rápidos para operación.</div>
              </div>
              <span class="pill info">Cuenta</span>
            </div>
            <div class="bd" style="display:grid;gap:10px;">
              <div class="note">
                ID: <span class="mono">{{ $account->id }}</span>
                <small>Referencia para pagos/soporte.</small>
              </div>

              <div class="note">
                Cliente: <b>{{ $name }}</b>
                <small>RFC {{ $rfc }} · {{ $email }}</small>
              </div>

              <a class="btn primary" href="{{ $stateUrl }}">Abrir estado ({{ $periodNow }})</a>
            </div>
          </div>
        </div>

      </div>
    @endif

    {{-- MODULES --}}
    @if($tab === 'modules')
      <div class="grid">

        <div class="card">
          <div class="hd">
            <div>
              <div class="h">Módulos (SOT)</div>
              <div class="s">
                Se guarda como <b>modules_state[key]=active|inactive|hidden|blocked</b> en <span class="mono">meta.modules_state</span>.
              </div>
            </div>
            <span class="pill info">meta.modules_state</span>
          </div>

          <div class="bd">
            <div class="modsHead">
              <div class="lhs">
                <span class="pill neu">Total: <span class="mono" id="modsTotal">{{ $totalModules }}</span></span>
                <span class="statePill" data-st="active">Activos: <span class="mono" id="cntActive">{{ $counts['active'] }}</span></span>
                <span class="statePill" data-st="inactive">Inactivos: <span class="mono" id="cntInactive">{{ $counts['inactive'] }}</span></span>
                <span class="statePill" data-st="hidden">Ocultos: <span class="mono" id="cntHidden">{{ $counts['hidden'] }}</span></span>
                <span class="statePill" data-st="blocked">Bloqueados: <span class="mono" id="cntBlocked">{{ $counts['blocked'] }}</span></span>
              </div>
              <div class="rhs">
                <input class="in" id="modsSearch" type="text" placeholder="Buscar módulo (label, key, grupo)..." style="max-width:360px;">
                <button type="button" class="btn soft" data-mass="active">Activar todo</button>
                <button type="button" class="btn soft" data-mass="inactive">Inactivar todo</button>
                <button type="button" class="btn soft" data-mass="hidden">Ocultar todo</button>
                <button type="button" class="btn soft" data-mass="blocked">Bloquear todo</button>
              </div>
            </div>

            <form method="POST" action="{{ route('admin.billing.accounts.modules', $account->id) }}" class="form" id="modsForm">
              @csrf

              <div class="help" style="margin-top:-2px;">
                Reglas en Cliente:
                <b>active</b> = visible y usable,
                <b>inactive</b> = visible pero deshabilitado,
                <b>hidden</b> = no aparece en menú,
                <b>blocked</b> = visible pero bloqueado (lock duro).
              </div>

              <div class="mods" id="modsList">
                @foreach($grouped as $groupName => $items)
                  <div class="groupTitle" data-group="{{ e($groupName) }}">
                    <div class="g">{{ $groupName }}</div>
                    <div class="c"><span class="mono" data-group-count>{{ count($items) }}</span> módulos</div>
                  </div>

                  @foreach($items as $k => $def)
                    @php
                      $label = (string)($def['label'] ?? $k);
                      $desc  = (string)($def['desc'] ?? '');
                      $st    = $modulesState[$k] ?? 'active';
                    @endphp

                    <div class="mod" data-mod data-key="{{ e($k) }}" data-label="{{ e($label) }}" data-group="{{ e($groupName) }}">
                      <div style="min-width:0;">
                        <div class="n">{{ $label }}</div>
                        <div class="d">
                          @if($desc !== '') {{ $desc }} · @endif
                          key: <b class="mono">{{ $k }}</b>
                        </div>
                      </div>

                      <div style="display:flex; gap:10px; align-items:center; justify-content:flex-end;">
                        <span class="statePill" data-state-pill data-st="{{ e($st) }}">{{ strtoupper($st) }}</span>

                        <select class="sel modState" name="modules_state[{{ $k }}]" data-state>
                          <option value="active"   @selected($st==='active')>Activo</option>
                          <option value="inactive" @selected($st==='inactive')>Inactivo</option>
                          <option value="hidden"   @selected($st==='hidden')>Oculto</option>
                          <option value="blocked"  @selected($st==='blocked')>Bloqueado</option>
                        </select>
                      </div>
                    </div>
                  @endforeach
                @endforeach
              </div>

              <button class="btn primary" type="submit">Guardar módulos (SOT)</button>

              <div class="help">
                Si guardas y el cliente no cambia, el siguiente paso es corregir <b>ClientSessionConfig::resolveAccountId()</b>
                para que cargue el <b>accountId SOT (mysql_admin.accounts.id)</b> correcto.
              </div>
            </form>
          </div>
        </div>

        <div class="stack">
          <div class="card">
            <div class="hd">
              <div>
                <div class="h">Nota</div>
                <div class="s">Operación recomendada.</div>
              </div>
              <span class="pill info">Tips</span>
            </div>
            <div class="bd" style="display:grid;gap:10px;">
              <div class="note">
                Tu log confirma que <b>modules_state</b> se guardó. Si el cliente “no refleja”, casi siempre es:
                <small>1) resolveAccountId incorrecto, 2) el usuario no apunta al admin_account_id, 3) cache (30s) + sesión vieja.</small>
              </div>

              <a class="btn primary" href="{{ $stateUrl }}">Abrir estado ({{ $periodNow }})</a>
            </div>
          </div>
        </div>

      </div>
    @endif

  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';

  const catalog = @json($catalog);

  const fmt = (n) => {
    n = parseInt(n||0, 10) || 0;
    return '$' + n.toLocaleString('es-MX') + ' MXN';
  };

  // =========================
  // Billing previews (null-safe)
  // =========================
  const priceKey = document.getElementById('price_key');
  const previewBase = document.getElementById('previewBase');

  const overrideMode = document.getElementById('override_mode');
  const overrideAmount = document.getElementById('override_amount_mxn');
  const overrideEffective = document.getElementById('override_effective');
  const previewPay = document.getElementById('previewPay');

  function setNoteVal(noteEl, valueStr){
    if (!noteEl) return;
    const span = noteEl.querySelector('[data-val]');
    if (span) span.textContent = valueStr;
  }

  function syncPayPreview(base){
    base = parseInt(base||0, 10) || 0;
    let eff = base;

    if (!overrideMode || !overrideAmount) return;

    const mode = overrideMode.value || 'none';
    const amt  = parseInt(overrideAmount.value || '', 10);

    if (mode === 'set' && !Number.isNaN(amt)) eff = Math.max(0, amt);
    setNoteVal(previewPay, fmt(eff));
  }

  function syncBasePreview(){
    if (!priceKey) return;
    const k = priceKey.value || '';
    const p = catalog[k] || {};
    const base = parseInt(p.amount_mxn || 0, 10) || 0;
    setNoteVal(previewBase, fmt(base));
    syncPayPreview(base);
  }

  function toggleOverrideInputs(){
    if (!overrideMode) return;
    const mode = overrideMode.value || 'none';
    const isSet = mode === 'set';
    if (overrideAmount) overrideAmount.disabled = !isSet;
    if (overrideEffective) overrideEffective.disabled = !isSet;
    syncBasePreview();
  }

  if (priceKey) priceKey.addEventListener('change', syncBasePreview);
  if (overrideMode) overrideMode.addEventListener('change', toggleOverrideInputs);
  if (overrideAmount) overrideAmount.addEventListener('input', syncBasePreview);

  syncBasePreview();
  toggleOverrideInputs();

  // =========================
  // Modules UX (SOT)
  // =========================
  const list = document.getElementById('modsList');
  if (!list) return;

  const search = document.getElementById('modsSearch');
  const mods = Array.from(list.querySelectorAll('[data-mod]'));
  const groupTitles = Array.from(list.querySelectorAll('.groupTitle'));

  const cntActive   = document.getElementById('cntActive');
  const cntInactive = document.getElementById('cntInactive');
  const cntHidden   = document.getElementById('cntHidden');
  const cntBlocked  = document.getElementById('cntBlocked');

  const norm = (s) => String(s||'').toLowerCase().trim();

  function paintPill(sel){
    const pill = sel.closest('[data-mod]')?.querySelector('[data-state-pill]');
    if (!pill) return;
    const st = sel.value || 'active';
    pill.setAttribute('data-st', st);
    pill.textContent = String(st).toUpperCase();
  }

  function recomputeCounts(){
    let a=0,i=0,h=0,b=0;
    mods.forEach(m => {
      if (m.classList.contains('is-hidden')) return;
      const sel = m.querySelector('[data-state]');
      const st = sel ? norm(sel.value || 'active') : 'active';
      if (st === 'active') a++;
      else if (st === 'inactive') i++;
      else if (st === 'hidden') h++;
      else if (st === 'blocked') b++;
    });
    if (cntActive) cntActive.textContent = String(a);
    if (cntInactive) cntInactive.textContent = String(i);
    if (cntHidden) cntHidden.textContent = String(h);
    if (cntBlocked) cntBlocked.textContent = String(b);
  }

  function syncGroupVisibility(){
    groupTitles.forEach(gt => {
      const g = gt.getAttribute('data-group') || '';
      const anyVisible = mods.some(m => {
        if (m.classList.contains('is-hidden')) return false;
        return (m.getAttribute('data-group') || '') === g;
      });
      gt.style.display = anyVisible ? '' : 'none';
    });
  }

  function applySearch(){
    const q = norm(search ? search.value : '');
    mods.forEach(m => {
      const key = norm(m.getAttribute('data-key'));
      const label = norm(m.getAttribute('data-label'));
      const group = norm(m.getAttribute('data-group'));
      const hit = !q || key.includes(q) || label.includes(q) || group.includes(q);
      m.classList.toggle('is-hidden', !hit);
    });
    syncGroupVisibility();
    recomputeCounts();
  }

  list.addEventListener('change', (e) => {
    const sel = e.target.closest('[data-state]');
    if (!sel) return;
    paintPill(sel);
    recomputeCounts();
  });

  if (search) search.addEventListener('input', applySearch);

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-mass]');
    if (!btn) return;

    const st = btn.getAttribute('data-mass') || 'active';

    mods.forEach(m => {
      // aplica SOLO a los visibles por búsqueda
      if (m.classList.contains('is-hidden')) return;
      const sel = m.querySelector('[data-state]');
      if (!sel) return;
      sel.value = st;
      paintPill(sel);
    });

    recomputeCounts();
  });

  // Init
  mods.forEach(m => {
    const sel = m.querySelector('[data-state]');
    if (sel) paintPill(sel);
  });
  applySearch();
})();
</script>
@endpush
