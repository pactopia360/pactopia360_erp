@extends('layouts.cliente')
@section('title','SAT · Descargas masivas CFDI')

@php
  // ===== Datos base seguros
  $plan     = strtoupper((string)($cuenta?->plan_actual ?? 'FREE'));
  $credList = $credList    ?? [];
  $rowsInit = $initialRows ?? [];

  // Modo DEMO/PROD por cookie/entorno
  $cookieMode  = request()->cookie('sat_mode');
  $driver      = config('services.sat.download.driver','satws');
  $isLocalDemo = app()->environment(['local','development','testing']) && $driver !== 'satws';
  $mode        = $cookieMode ? strtolower($cookieMode) : ($isLocalDemo ? 'demo' : 'prod');

  // Totales rápidos
  $kRfc   = is_countable($credList) ? count($credList) : 0;
  $kPend  = 0;
  $kReady = 0;

  if (is_iterable($rowsInit)) {
      foreach ($rowsInit as $r) {
          $estado = strtolower($r['estado'] ?? '');
          if (in_array($estado, ['pending','processing'])) {
              $kPend++;
          }
          if (in_array($estado, ['ready','done','listo'])) {
              $kReady++;
          }
      }
  }

  $asig   = (int)($cuenta->sat_quota_assigned ?? ($plan==='PRO' ? 12 : 1));
  $usadas = (int)($cuenta->sat_quota_used ?? 0);
  $pct    = $asig > 0 ? min(100, max(0, round(($usadas / $asig) * 100))) : 0;

  // Rutas (defensivas)
  $rtCsdStore  = \Route::has('cliente.sat.credenciales.store') ? route('cliente.sat.credenciales.store') : '#';
  $rtReqCreate = \Route::has('cliente.sat.request')            ? route('cliente.sat.request')            : '#';
  $rtVerify    = \Route::has('cliente.sat.verify')             ? route('cliente.sat.verify')             : '#';
  $rtPkgPost   = \Route::has('cliente.sat.download')           ? route('cliente.sat.download')           : '#';
  $rtZipGet    = \Route::has('cliente.sat.zip')                ? route('cliente.sat.zip',['id'=>'__ID__']) : '#';
  $rtReport    = \Route::has('cliente.sat.report')             ? route('cliente.sat.report')             : '#';
  $rtVault     = \Route::has('cliente.sat.vault')              ? route('cliente.sat.vault')              : '#';
  $rtMode      = \Route::has('cliente.sat.mode')               ? route('cliente.sat.mode')               : null;

  // Rutas para RFCs (partial)
  $rtAlias     = \Route::has('cliente.sat.alias')        ? route('cliente.sat.alias')         : '#';
  $rtRfcReg    = \Route::has('cliente.sat.rfc.register') ? route('cliente.sat.rfc.register')  : '#';
@endphp


@push('styles')
<style>
/* =====================================================
   PACTOPIA360 · SAT · CLEAN STACK UI
   ===================================================== */
.sat-ui{
  --brand:#e11d48;
  --brand-soft:#fee2e2;
  --ok:#16a34a;
  --ok-soft:#dcfce7;
  --warn:#ea580c;
  --warn-soft:#ffedd5;
  --err:#ef4444;
  --err-soft:#fee2e2;
  --ink:#0f172a;
  --mut:#6b7280;
  --card:#ffffff;
  --bg:#f9fafb;
  --bd:#e5e7eb;
  --bd-soft:#f1f5f9;
  --radius-card:18px;
  --radius-pill:999px;
  --shadow-soft:0 10px 30px rgba(15,23,42,.04);

  max-width:1200px;
  margin:0 auto;
  display:flex;
  flex-direction:column;
  gap:18px;
}

html[data-theme="dark"] .sat-ui{
  --ink:#e5e7eb;
  --mut:#9ca3af;
  --card:#020617;
  --bg:#020617;
  --bd:#1f2937;
  --bd-soft:#111827;
  --shadow-soft:0 18px 50px rgba(0,0,0,.6);
}

/* Tarjetas base */
.sat-card{
  background:var(--card);
  border-radius:var(--radius-card);
  border:1px solid var(--bd-soft);
  box-shadow:var(--shadow-soft);
  padding:16px 18px;
}
.sat-card + .sat-card{
  margin-top:2px;
}

/* Header principal */
.sat-header-top{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:space-between;
  gap:10px;
}
.sat-title-wrap{
  display:flex;
  align-items:center;
  gap:10px;
}
.sat-icon{
  width:32px;height:32px;
  border-radius:12px;
  background:#fee2e2;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#be123c;
}
html[data-theme="dark"] .sat-icon{
  background:#4b1120;
}
.sat-title-main{
  font:900 20px/1.2 'Poppins',system-ui;
  color:var(--ink);
}
.sat-title-sub{
  font-size:11px;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--mut);
}

/* Zona de acciones (Actualizar / CSV / Excel / Reporte / Modo) */
.sat-actions{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  align-items:center;
}

/* Botón modo DEMO/PROD */
.badge-mode{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:6px 12px;
  border-radius:var(--radius-pill);
  border:1px solid var(--bd);
  font:800 10.5px/1 'Poppins';
  text-transform:uppercase;
  letter-spacing:.12em;
  cursor:pointer;
}
.badge-mode .dot{
  width:7px;height:7px;border-radius:999px;
}
.badge-mode.demo{
  background:#fef9c3;
  border-color:#fef3c7;
  color:#854d0e;
}
.badge-mode.demo .dot{background:#facc15;}
.badge-mode.prod{
  background:#dcfce7;
  border-color:#bbf7d0;
  color:#166534;
}
.badge-mode.prod .dot{background:#22c55e;}

html[data-theme="dark"] .badge-mode.demo{
  background:#4b3b07;
  border-color:#78350f;
  color:#facc15;
}
html[data-theme="dark"] .badge-mode.prod{
  background:#052e16;
  border-color:#15803d;
  color:#bbf7d0;
}

/* Botones */
.btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  padding:7px 12px;
  border-radius:var(--radius-pill);
  border:1px solid var(--bd);
  background:var(--card);
  color:var(--ink);
  font:800 12px/1 'Poppins',system-ui;
  min-height:34px;
  cursor:pointer;
  transition:background .12s ease,transform .12s ease,box-shadow .12s ease,border-color .12s ease,opacity .1s ease;
}
.btn span[aria-hidden="true"]{font-size:15px}
.btn:hover:not(:disabled){
  background:#f9fafb;
  transform:translateY(-1px);
  box-shadow:0 8px 20px rgba(15,23,42,.06);
}
.btn.icon{
  width:34px;height:34px;padding:0;
}
.btn.primary{
  background:var(--brand);
  border-color:var(--brand);
  color:#fff;
}
.btn.primary:hover:not(:disabled){
  background:#be123c;
}
.btn.soft{
  background:#fee2e2;
  border-color:#fecaca;
}
html[data-theme="dark"] .btn.soft{
  background:#4b1120;
  border-color:#9f1239;
}
.btn:disabled{
  opacity:.55;
  cursor:not-allowed;
  box-shadow:none;
  transform:none;
}

/* Tooltips */
[data-tip]{position:relative}
[data-tip]::after{
  content:attr(data-tip);
  position:absolute;
  bottom:110%;
  left:50%;
  transform:translateX(-50%);
  background:#0f172a;
  color:#f9fafb;
  padding:5px 8px;
  border-radius:8px;
  font:700 11px/1 'Poppins';
  white-space:nowrap;
  opacity:0;
  pointer-events:none;
  transition:opacity .1s ease;
  z-index:20;
}
[data-tip]::before{
  content:"";
  position:absolute;
  bottom:104%;
  left:50%;
  transform:translateX(-50%);
  border:6px solid transparent;
  border-top-color:#0f172a;
  opacity:0;
  transition:opacity .1s ease;
  z-index:20;
}
[data-tip]:hover::after,
[data-tip]:hover::before{opacity:1}

/* KPIs */
.sat-kpis{
  display:grid;
  gap:10px;
  margin-top:14px;
}
@media(min-width:900px){
  .sat-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}
}
.kpi{
  background:#fdf2f8;
  border-radius:14px;
  border:1px solid var(--bd-soft);
  padding:10px 11px;
}
html[data-theme="dark"] .kpi{
  background:#111827;
  border-color:#1f2937;
}
.kpi-label{
  font-size:11px;
  font-weight:800;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--mut);
}
.kpi-value{
  font:900 20px/1.1 'Poppins';
  color:var(--ink);
  margin-top:2px;
}
.kpi-foot{
  margin-top:4px;
  font-size:11px;
  font-weight:600;
  color:var(--mut);
}
.kpi-bar{
  margin-top:4px;
  height:6px;
  border-radius:999px;
  background:rgba(148,163,184,.3);
  overflow:hidden;
}
.kpi-bar span{
  display:block;
  height:100%;
  background:linear-gradient(90deg,#fb7185,var(--brand));
}

/* Charts */
.tabs{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}
.tab{
  border-radius:var(--radius-pill);
  border:1px solid var(--bd);
  background:var(--card);
  padding:5px 11px;
  font:800 11px/1 'Poppins';
  cursor:pointer;
}
.tab.is-active{
  background:var(--brand);
  border-color:var(--brand);
  color:#fff;
}
.chart-wrap{
  display:grid;
  gap:10px;
  margin-top:12px;
}
@media(min-width:900px){
  .chart-wrap{
    grid-template-columns:1.1fr 1fr;
  }
}
.canvas-card{
  height:220px;
  border-radius:14px;
  border:1px solid var(--bd-soft);
  background:var(--card);
  padding:10px;
}

/* Guías / pills */
.pills-row{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
}
.pill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:7px 12px;
  border-radius:var(--radius-pill);
  border:1px solid var(--bd-soft);
  background:#fdf2f8;
  font:800 11px/1 'Poppins';
  color:var(--ink);
}
.pill.primary{
  background:var(--brand);
  border-color:var(--brand);
  color:#fff;
}
.pill span[aria-hidden="true"]{font-size:15px}
html[data-theme="dark"] .pill{
  background:#111827;
  border-color:#1f2937;
}

/* Tabla genérica */
.table-wrap{
  margin-top:12px;
  border-radius:14px;
  border:1px solid var(--bd-soft);
  overflow:auto;
  background:var(--card);
}
.table{
  width:100%;
  border-collapse:collapse;
  font-size:13px;
}
.table th{
  background:#fef2f2;
  color:#64748b;
  text-align:left;
  padding:9px 10px;
  font-size:11px;
  font-weight:900;
  letter-spacing:.11em;
  text-transform:uppercase;
  border-bottom:1px solid var(--bd-soft);
}
html[data-theme="dark"] .table th{
  background:#4b1120;
}
.table td{
  padding:8px 10px;
  border-bottom:1px solid rgba(148,163,184,.2);
}
.table tr:hover td{
  background:#f9fafb;
}
html[data-theme="dark"] .table tr:hover td{
  background:#020617;
}
.t-right{text-align:right}
.empty{
  text-align:center;
  padding:14px 10px;
  font-weight:800;
  color:var(--mut);
}

/* Badges / tags */
.badge-status{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:3px 9px;
  border-radius:var(--radius-pill);
  font:800 11px/1 'Poppins';
}
.badge-status.ok{background:var(--ok-soft);color:var(--ok)}
.badge-status.warn{background:var(--warn-soft);color:var(--warn)}
.badge-status.err{background:var(--err-soft);color:var(--err)}
.tag{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 9px;
  border-radius:var(--radius-pill);
  border:1px solid var(--bd-soft);
  background:#f9fafb;
  font:800 11px/1 'Poppins';
}
.tag::before{content:"•";color:var(--brand)}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace}

/* Filtros / inputs */
.filters{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  align-items:center;
}
.chip-plan{
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:6px 11px;
  border-radius:var(--radius-pill);
  border:1px solid var(--bd-soft);
  background:#fdf2f8;
  font:800 11px/1 'Poppins';
  color:var(--ink);
}
.chip-plan span[aria-hidden="true"]{font-size:15px}
html[data-theme="dark"] .chip-plan{
  background:#111827;
  border-color:#1f2937;
}
.input,.select{
  border-radius:12px;
  border:1px solid var(--bd);
  background:var(--card);
  color:var(--ink);
  padding:7px 9px;
  font:800 12px/1 'Poppins';
  min-height:34px;
}
.input::placeholder{color:var(--mut)}

/* Totales bóveda */
.v-totals{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:10px;
}
.v-pill{
  border-radius:var(--radius-pill);
  border:1px solid var(--bd-soft);
  background:#f9fafb;
  padding:7px 11px;
  font:800 12px/1 'Poppins';
}
.v-pill b{font-weight:900}
html[data-theme="dark"] .v-pill{
  background:#111827;
  border-color:#1f2937;
}

/* Automatizadas */
.auto-grid{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}
.auto-btn{
  min-width:120px;
}
.badge-plan-lock,
.badge-plan-ok{
  border-radius:var(--radius-pill);
  padding:6px 11px;
  font:800 11px/1 'Poppins';
}
.badge-plan-lock{
  background:#fef2f2;
  border:1px solid #fecaca;
  color:#b91c1c;
}
.badge-plan-ok{
  background:#dcfce7;
  border:1px solid #bbf7d0;
  color:#166534;
}
html[data-theme="dark"] .badge-plan-lock{
  background:#4b1120;
  border-color:#9f1239;
  color:#fecaca;
}
html[data-theme="dark"] .badge-plan-ok{
  background:#052e16;
  border-color:#16a34a;
  color:#bbf7d0;
}
.auto-grid.is-locked .btn{
  opacity:.5;
  cursor:not-allowed;
}

/* Textos pequeños */
.text-muted{color:var(--mut);font-size:11px}
.section-title{
  font:800 13px/1.2 'Poppins';
  color:var(--ink);
  margin-bottom:4px;
}
.section-sub{
  font-size:11px;
  font-weight:600;
  color:var(--mut);
}
</style>
@endpush


@section('content')
<div class="sat-ui" id="satApp" data-plan="{{ $plan }}" data-mode="{{ $mode }}">

  {{-- 1) TÍTULO + KPIs --}}
  <div class="sat-card">
    <div class="sat-header-top">
      <div class="sat-title-wrap">
        <div class="sat-icon">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="4" y="4" width="16" height="16" rx="3"></rect>
            <path d="M4 10h16M10 4v4"></path>
          </svg>
        </div>
        <div>
          <div class="sat-title-main">Descargas masivas CFDI</div>
          <div class="sat-title-sub">CFDI EMITIDOS Y RECIBIDOS · CONTROL CENTRAL</div>
        </div>
      </div>

      <div class="sat-actions">
        <button class="btn" type="button" id="btnRefresh" data-tip="Actualizar pantalla">
          <span aria-hidden="true">⟳</span><span>Actualizar</span>
        </button>
        <a class="btn" href="{{ $rtReport }}?fmt=csv" data-tip="Descargar reporte CSV">
          <span aria-hidden="true">🗒</span><span>CSV</span>
        </a>
        <a class="btn" href="{{ $rtReport }}?fmt=xlsx" data-tip="Descargar reporte Excel">
          <span aria-hidden="true">📑</span><span>Excel</span>
        </a>
        <a class="btn soft" href="{{ $rtReport }}" data-tip="Ir al reporte contable">
          <span aria-hidden="true">📊</span><span>Reporte</span>
        </a>
        @if($rtMode)
          <button
            type="button"
            class="badge-mode {{ $mode==='demo' ? 'demo' : 'prod' }}"
            id="badgeMode"
            data-url="{{ $rtMode }}"
          >
            <span class="dot"></span>
            <span>{{ strtoupper($mode)==='PROD' ? 'PRODUCCIÓN' : 'DEMO' }}</span>
          </button>
        @endif
      </div>
    </div>

    <div class="sat-kpis">
      <div class="kpi" data-tip="RFCs con credenciales SAT activas">
        <div class="kpi-label">RFCs conectados</div>
        <div class="kpi-value">{{ $kRfc }}</div>
        <div class="kpi-foot">Credenciales registradas</div>
      </div>
      <div class="kpi" data-tip="Solicitudes en proceso">
        <div class="kpi-label">Pendientes</div>
        <div class="kpi-value">{{ $kPend }}</div>
        <div class="kpi-foot">En proceso</div>
      </div>
      <div class="kpi" data-tip="Solicitudes listas para descarga">
        <div class="kpi-label">Listas</div>
        <div class="kpi-value">{{ $kReady }}</div>
        <div class="kpi-foot">ZIP disponibles</div>
      </div>
      <div class="kpi" data-tip="Uso de solicitudes de descarga">
        <div class="kpi-label">Uso de cuota</div>
        <div class="kpi-value">{{ $pct }}%</div>
        <div class="kpi-foot">
          {{ $usadas }}/{{ $asig }} usadas
          <div class="kpi-bar"><span style="width:{{ $pct }}%"></span></div>
        </div>
      </div>
    </div>
  </div>

  {{-- 2) PANEL DE SOLICITUDES --}}
  <div class="sat-card">
    <div style="margin-bottom:10px">
      <div class="section-title">Panel de solicitudes</div>
      <div class="section-sub">Genera nuevas solicitudes de descarga manuales</div>
    </div>

    @if($plan==='FREE')
      <div class="chip-plan" data-tip="FREE: 1 solicitud activa, periodos máximo 1 mes">
        <span aria-hidden="true">🆓</span>
        <span>Plan FREE · 1 solicitud · ≤ 1 mes</span>
      </div>
    @else
      <div class="chip-plan" data-tip="PRO: hasta 12 solicitudes por RFC">
        <span aria-hidden="true">⭐</span>
        <span>Plan PRO · hasta 12 solicitudes por RFC</span>
      </div>
    @endif

    <form id="reqForm" method="post" action="{{ $rtReqCreate }}" class="filters" style="margin-top:10px">
      @csrf
      <select class="select" name="tipo" aria-label="Tipo de CFDI">
        <option value="emitidos">Emitidos</option>
        <option value="recibidos">Recibidos</option>
        <option value="ambos">Ambos</option>
      </select>
      <input class="input" type="date" name="date_from" aria-label="Desde">
      <input class="input" type="date" name="date_to" aria-label="Hasta">
      <select class="select" name="rfc" aria-label="RFC">
        <option value="">RFC</option>
        @foreach($credList as $c)
          @php $rf = strtoupper($c['rfc'] ?? $c->rfc ?? ''); @endphp
          <option value="{{ $rf }}">{{ $rf }}</option>
        @endforeach
      </select>

      <button class="btn primary" type="submit" data-tip="Crear solicitud">
        <span aria-hidden="true">⤵️</span>
      </button>
      <button type="button" class="btn" id="btnSatVerify" data-tip="Verificar estado de solicitudes">
        <span aria-hidden="true">🔄</span><span>Verificar</span>
      </button>
      <a href="{{ $rtVault }}" class="btn" data-tip="Ir a Bóveda Fiscal">
        <span aria-hidden="true">💼</span><span>Bóveda</span>
      </a>
    </form>
  </div>

  {{-- 3) AUTOMATIZADAS --}}
  <div class="sat-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px">
      <div>
        <div class="section-title">Automatizadas</div>
        <div class="section-sub">Programación de descargas periódicas por RFC</div>
      </div>
      @if($plan==='FREE')
        <div class="badge-plan-lock">Solo PRO</div>
      @else
        <div class="badge-plan-ok">Incluido en tu plan PRO</div>
      @endif
    </div>

    <div class="auto-grid {{ $plan==='FREE' ? 'is-locked' : '' }}">
      <button type="button" class="btn auto-btn" {{ $plan==='FREE' ? 'disabled' : '' }}>
        <span aria-hidden="true">⏱</span><span>Diario</span>
      </button>
      <button type="button" class="btn auto-btn" {{ $plan==='FREE' ? 'disabled' : '' }}>
        <span aria-hidden="true">📅</span><span>Semanal</span>
      </button>
      <button type="button" class="btn auto-btn" {{ $plan==='FREE' ? 'disabled' : '' }}>
        <span aria-hidden="true">🗓</span><span>Mensual</span>
      </button>
      <button type="button" class="btn auto-btn" {{ $plan==='FREE' ? 'disabled' : '' }}>
        <span aria-hidden="true">⚙️</span><span>Por rango</span>
      </button>
    </div>

    <p class="text-muted" style="margin-top:8px">
      @if($plan==='FREE')
        Estas opciones se activan al contratar el plan PRO. Cada ejecución automática consume solicitudes de tu cuota contratada.
      @else
        Cada ejecución automática consumirá solicitudes de tu cuota contratada de descargas SAT.
      @endif
    </p>
  </div>

  {{-- 4) GRÁFICAS --}}
  <div class="sat-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
      <div>
        <div class="section-title">Tendencias</div>
        <div class="section-sub">Movimientos recientes de CFDI</div>
      </div>
      <div class="tabs">
        <button class="tab is-active" data-scope="emitidos">Emitidos</button>
        <button class="tab" data-scope="recibidos">Recibidos</button>
        <button class="tab" data-scope="ambos">Ambos</button>
      </div>
    </div>

    <div class="chart-wrap">
      <div class="canvas-card"><canvas id="chartA"></canvas></div>
      <div class="canvas-card"><canvas id="chartB"></canvas></div>
    </div>

    <p class="text-muted" style="margin-top:8px">
      Gráficas ilustrativas. Puedes conectar aquí tus datos reales de bóveda o reportes contables.
    </p>
  </div>

  {{-- 5) GUÍAS RÁPIDAS --}}
  <div class="sat-card">
    <div style="margin-bottom:8px">
      <div class="section-title">Guías rápidas</div>
      <div class="section-sub">Flujos frecuentes de trabajo</div>
    </div>
    <div class="pills-row">
      <div class="pill primary" data-tip="Crear nueva solicitud de descarga">
        <span aria-hidden="true">🧾</span><span>Solicitar CFDI</span>
      </div>
      <div class="pill" data-tip="Ir a automatizadas">
        <span aria-hidden="true">⚙️</span><span>Descargas automatizadas</span>
      </div>
      <a class="pill" href="{{ $rtVault }}" data-tip="Ir a la Bóveda Fiscal">
        <span aria-hidden="true">💼</span><span>Bóveda fiscal</span>
      </a>
    </div>
  </div>

  {{-- 6) RFCs REGISTRADOS --}}
  <div class="sat-card">
    <div style="margin-bottom:8px">
      <div class="section-title">Conexiones SAT</div>
      <div class="section-sub">RFCs y certificados CSD registrados</div>
    </div>

    @include('cliente.sat._partials.rfcs', [
      'credList'   => $credList,
      'plan'       => $plan,
      'rtCsdStore' => $rtCsdStore,
      'rtAlias'    => $rtAlias,
      'rtRfcReg'   => $rtRfcReg,
    ])
  </div>

  {{-- 7) LISTADO DE DESCARGAS SAT --}}
  <div class="sat-card">
    <div style="margin-bottom:10px">
      <div class="section-title">Listado de descargas SAT</div>
      <div class="section-sub">Histórico de solicitudes y paquetes generados</div>
    </div>

    <div class="table-wrap">
      <table class="table" aria-label="Solicitudes recientes">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tipo</th>
            <th>Periodo</th>
            <th>Estado</th>
            <th>Paquete</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rowsInit as $r)
            @php
              $estado = strtolower($r['estado']??'');
              $ok  = str_contains($estado,'ready') || str_contains($estado,'done') || str_contains($estado,'listo');
              $cls = $ok ? 'ok' : (str_contains($estado,'error')||str_contains($estado,'fail') ? 'err' : 'warn');
            @endphp
            <tr>
              <td>{{ $r['dlid']??'—' }}</td>
              <td><span class="tag">{{ ucfirst($r['tipo']??'') }}</span></td>
              <td>{{ $r['desde']??'' }} → {{ $r['hasta']??'' }}</td>
              <td><span class="badge-status {{ $cls }}">{{ $r['estado']??'' }}</span></td>
              <td class="mono">{{ $r['package_id']??'—' }}</td>
              <td>
                @if($ok && ($r['dlid']??false))
                  <a class="btn icon" data-tip="Descargar ZIP"
                     href="{{ str_replace('__ID__',$r['dlid'],$rtZipGet) }}">⬇️</a>
                @else
                  <form method="post" action="{{ $rtPkgPost }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="download_id" value="{{ $r['dlid']??'' }}">
                    <button class="btn icon" data-tip="Reintentar creación de paquete">↻</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="empty">Aún no has generado solicitudes de descarga.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- 8) BÓVEDA FISCAL (resumen rápido) --}}
  <div class="sat-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px">
      <div>
        <div class="section-title">Vista rápida · Bóveda fiscal</div>
        <div class="section-sub">Resumen de CFDI guardados</div>
      </div>
      <a class="btn soft" href="{{ $rtVault }}" data-tip="Abrir bóveda completa">
        <span aria-hidden="true">↗</span><span>Ver bóveda</span>
      </a>
    </div>

    <form class="filters" style="margin-bottom:8px">
      <select class="select" aria-label="Tipo CFDI">
        <option>Ambos</option>
        <option>Emitidos</option>
        <option>Recibidos</option>
      </select>
      <input class="input" type="date" aria-label="Desde">
      <input class="input" type="date" aria-label="Hasta">
      <input class="input" type="text" placeholder="RFC / UUID / Razón social" aria-label="Buscar">
      <button class="btn" type="button">
        <span aria-hidden="true">🔍</span><span>Filtrar</span>
      </button>
    </form>

    <div class="v-totals">
      <div class="v-pill">CFDI: <b id="tCnt">0</b></div>
      <div class="v-pill">Subtotal: <b id="tSub">$0.00</b></div>
      <div class="v-pill">IVA: <b id="tIva">$0.00</b></div>
      <div class="v-pill">Total: <b id="tTot">$0.00</b></div>
    </div>

    <div class="table-wrap" style="margin-top:8px">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>RFC</th>
            <th>Razón social</th>
            <th class="t-right">Total</th>
            <th>UUID</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6" class="empty">
              Vista rápida. Usa la Bóveda Fiscal para navegar y descargar CFDI a detalle.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* ===== Cambiar DEMO/PROD con el botón de modo ===== */
(() => {
  const badge = document.getElementById('badgeMode');
  if (!badge || !badge.dataset.url) return;
  badge.addEventListener('click', async () => {
    try{
      const res = await fetch(badge.dataset.url, {
        method:'POST',
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':'{{ csrf_token() }}'
        }
      });
      if(!res.ok) throw new Error('HTTP '+res.status);
      location.reload();
    }catch(e){
      alert('No se pudo cambiar el modo');
    }
  });
})();

/* ===== Actualizar pantalla ===== */
(() => {
  const btn = document.getElementById('btnRefresh');
  if(!btn) return;
  btn.addEventListener('click', () => {
    location.reload();
  });
})();

/* ===== Verificar solicitudes ===== */
(() => {
  const btn = document.getElementById('btnSatVerify');
  if(!btn) return;
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    try{
      const r = await fetch("{{ $rtVerify }}", {
        headers:{'X-Requested-With':'XMLHttpRequest'}
      });
      const j = await r.json();
      alert(`Pendientes: ${j.pending ?? 0} · Listos: ${j.ready ?? 0}`);
      location.reload();
    }catch(e){
      alert('No se pudo verificar');
    }finally{
      btn.disabled = false;
    }
  });
})();

/* ===== Regla FREE: ≤ 1 mes ===== */
(() => {
  const plan = "{{ $plan }}";
  const f = document.getElementById('reqForm');
  if(!f || plan!=='FREE') return;
  f.addEventListener('submit', ev=>{
    const d = new FormData(f);
    const a = new Date(d.get('date_from'));
    const b = new Date(d.get('date_to'));
    if(isNaN(+a) || isNaN(+b)) return;
    if((b - a) > 32*24*3600*1000){
      ev.preventDefault();
      alert('En FREE sólo puedes solicitar hasta 1 mes.');
    }
  });
})();

/* ===== Charts dummy ===== */
(() => {
  const mk = (id,label)=> {
    const el = document.getElementById(id);
    if(!el) return null;
    return new Chart(el, {
      type:'line',
      data:{labels:[],datasets:[{label,data:[],borderWidth:2,tension:.35}]},
      options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:true}},
        scales:{
          x:{grid:{display:false}},
          y:{grid:{color:'rgba(148,163,184,.25)'}}
        }
      }
    });
  };

  const A = mk('chartA','Importe total');
  const B = mk('chartB','# CFDI');
  if(!A || !B) return;

  const reload = (scope) => {
    const labels = Array.from({length:6},(_,i)=>`M-${i+1}`);
    const rnd  = () => labels.map(()=> Math.round(Math.random()*1000));
    A.data.labels = labels;
    B.data.labels = labels;
    A.data.datasets[0].data = rnd();
    B.data.datasets[0].data = rnd();
    A.update(); B.update();
  };

  reload('emitidos');

  document.querySelectorAll('.tab').forEach(t=>{
    t.addEventListener('click',()=>{
      document.querySelectorAll('.tab').forEach(x=>x.classList.remove('is-active'));
      t.classList.add('is-active');
      reload(t.dataset.scope);
    });
  });
})();
</script>
@endpush
