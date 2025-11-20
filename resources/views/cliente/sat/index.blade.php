@extends('layouts.cliente')
@section('title','SAT · Descargas masivas CFDI')

@php
  // ===== Resumen unificado de cuenta (admin.accounts) =====
  // Reutilizamos el helper del Home para que el layout y este módulo
  // vean el mismo plan (FREE / PRO / etc.)
  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
  $isProSummary    = (bool)($summary['is_pro'] ?? in_array(
      strtolower($planFromSummary),
      ['pro','premium','empresa','business'],
      true
  ));

  // ===== Datos base seguros (SAT) =====
  $plan      = $planFromSummary;
  $isProPlan = $isProSummary;   // bandera principal para FREE / PRO en este módulo
  $isPro     = $isProPlan;      // alias por compatibilidad si se usa en otra parte

  $credList = $credList    ?? [];
  $rowsInit = $initialRows ?? [];

  // Modo DEMO/PROD por cookie/entorno
  $cookieMode  = request()->cookie('sat_mode');
  $driver      = config('services.sat.download.driver','satws');
  $isLocalDemo = app()->environment(['local','development','testing']) && $driver !== 'satws';
  $mode        = $cookieMode ? strtolower($cookieMode) : ($isLocalDemo ? 'demo' : 'prod');
  $modeLabel   = strtoupper($mode === 'demo' ? 'FUENTE: DEMO' : 'FUENTE: PRODUCCIÓN');

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

  // Cuotas SAT: si es PRO usa 12, si no 1 (ajusta según tus reglas reales)
  $asigDefault = $isProPlan ? 12 : 1;
  $asig        = (int)($cuenta->sat_quota_assigned ?? $asigDefault);
  $usadas      = (int)($cuenta->sat_quota_used ?? 0);
  $pct         = $asig > 0 ? min(100, max(0, round(($usadas / $asig) * 100))) : 0;

  // Rutas (defensivas)
  $rtCsdStore  = \Route::has('cliente.sat.credenciales.store') ? route('cliente.sat.credenciales.store') : '#';
  $rtReqCreate = \Route::has('cliente.sat.request')            ? route('cliente.sat.request')            : '#';
  $rtVerify    = \Route::has('cliente.sat.verify')             ? route('cliente.sat.verify')             : '#';
  $rtPkgPost   = \Route::has('cliente.sat.download')           ? route('cliente.sat.download')           : '#';
  $rtZipGet    = \Route::has('cliente.sat.zip')                ? route('cliente.sat.zip',['id'=>'__ID__']) : '#';
  $rtReport    = \Route::has('cliente.sat.report')             ? route('cliente.sat.report')             : '#';
  $rtVault     = \Route::has('cliente.sat.vault')              ? route('cliente.sat.vault')              : '#';
  $rtMode      = \Route::has('cliente.sat.mode')               ? route('cliente.sat.mode')               : null;
  $rtCharts    = \Route::has('cliente.sat.charts')             ? route('cliente.sat.charts')             : null;

  // Rutas para RFCs (partial / modal)
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

/* Zona de acciones */
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

/* ==========================
   MODAL AGREGAR RFC / CSD
   ========================== */
.sat-modal-backdrop{
  position:fixed;
  inset:0;
  display:none;
  align-items:center;
  justify-content:center;
  background:rgba(15,23,42,.38);
  z-index:80;
}
.sat-modal-backdrop.is-open{display:flex;}

html[data-theme="dark"] .sat-modal-backdrop{
  background:rgba(0,0,0,.70);
}

.sat-modal{
  width:100%;
  max-width:540px;
  background:var(--card);
  border-radius:18px;
  border:1px solid var(--bd-soft);
  box-shadow:var(--shadow-soft);
  padding:18px 20px 16px;
}

.sat-modal-header{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  margin-bottom:12px;
}

.sat-modal-kicker{
  font:800 10px/1 'Poppins';
  text-transform:uppercase;
  letter-spacing:.16em;
  color:var(--mut);
  margin-bottom:4px;
}

.sat-modal-title{
  font:900 19px/1.2 'Poppins';
  color:var(--ink);
}

.sat-modal-sub{
  margin:4px 0 0;
  font-size:12px;
  font-weight:500;
  color:var(--mut);
}

.sat-modal-close{
  border:0;
  background:transparent;
  width:28px;
  height:28px;
  border-radius:999px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:14px;
  cursor:pointer;
  color:var(--mut);
}
.sat-modal-close:hover{
  background:#f3f4f6;
  color:var(--ink);
}
html[data-theme="dark"] .sat-modal-close:hover{
  background:#111827;
}

.sat-modal-body{
  padding-top:4px;
  padding-bottom:6px;
  display:flex;
  flex-direction:column;
  gap:10px;
}

.sat-field{
  display:flex;
  flex-direction:column;
  gap:4px;
}

.sat-field-label{
  font:800 11px/1.1 'Poppins';
  text-transform:uppercase;
  letter-spacing:.12em;
  color:var(--mut);
}

.sat-modal .input{
  width:100%;
  border-radius:12px;
  border:1px solid var(--bd);
  background:var(--bg);
  padding:8px 10px;
  font:800 13px/1 'Poppins';
  color:var(--ink);
}
.sat-modal .input::placeholder{
  color:#9ca3af;
  font-weight:500;
}
.sat-modal .input[type="file"]{
  padding:6px 10px;
  font-weight:600;
  font-size:12px;
}

.sat-field-inline{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px 16px;
}

.sat-modal-sep{
  border:0;
  border-top:1px dashed var(--bd);
  margin:4px 0 4px;
}

.sat-modal-note{
  margin:4px 0 0;
  font-size:11px;
  font-weight:600;
  color:var(--mut);
}

.sat-modal-footer{
  margin-top:8px;
  display:flex;
  justify-content:flex-end;
  gap:8px;
}
@media(max-width:640px){
  .sat-modal{
    margin:0 10px;
    padding:16px 14px 14px;
  }
  .sat-field-inline{
    grid-template-columns:1fr;
  }
}

/* ==========================
   LISTADO DESCARGAS (mejorado)
   ========================== */
.sat-dl-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-end;
  gap:12px;
  margin-bottom:8px;
}
.sat-dl-title h3{
  margin:0;
  font:900 16px/1 'Poppins';
  color:var(--ink);
}
.sat-dl-title p{
  margin:2px 0 0;
  font-size:11px;
  color:var(--mut);
}
.sat-dl-filters{
  display:flex;
  flex-wrap:wrap;
  gap:6px;
  align-items:center;
}
.sat-dl-filters input,
.sat-dl-filters select{
  border-radius:999px;
  border:1px solid var(--bd);
  background:var(--card);
  padding:6px 10px;
  font-size:12px;
  font-family:'Poppins',system-ui;
}
.sat-dl-table-wrap{
  border-radius:14px;
  border:1px solid var(--bd-soft);
  overflow:hidden;
  background:var(--card);
}
.sat-dl-table{
  width:100%;
  border-collapse:collapse;
  font-size:12px;
  font-family:'Poppins',system-ui;
}
.sat-dl-table thead{
  background:#fef2f2;
  text-transform:uppercase;
  font-size:11px;
  letter-spacing:.08em;
  color:#9ca3af;
}
html[data-theme="dark"] .sat-dl-table thead{
  background:#4b1120;
}
.sat-dl-table th,
.sat-dl-table td{
  padding:8px 10px;
  border-bottom:1px solid rgba(148,163,184,.2);
  text-align:left;
  vertical-align:middle;
}
.sat-dl-table tbody tr:last-child td{border-bottom:0;}
.sat-badge-status{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:2px 8px;
  border-radius:999px;
  font-size:10px;
  font-weight:800;
  text-transform:uppercase;
}
.sat-badge-status.done{background:#dcfce7;color:#166534;}
.sat-badge-status.pending,
.sat-badge-status.processing{background:#fef3c7;color:#92400e;}
.sat-dl-btn-download{
  width:26px;
  height:26px;
  border-radius:999px;
  border:1px solid #e5e7eb;
  background:#eff6ff;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}

/* ========================== */
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
            <span>{{ $modeLabel }}</span>
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

    @if(!$isProPlan)
      <div class="chip-plan" data-tip="FREE: 1 solicitud activa, periodos máximo 1 mes">
        <span aria-hidden="true">🆓</span>
        <span>Plan FREE · 1 solicitud · ≤ 1 mes</span>
      </div>
    @else
      <div class="chip-plan" data-tip="Cuota contratada de descargas SAT">
        <span aria-hidden="true">✅</span>
        <span>Cuota actual · hasta {{ $asig }} solicitudes por RFC</span>
      </div>
    @endif

    <form id="reqForm" method="post" action="{{ $rtReqCreate }}" class="filters" style="margin-top:10px">
      @csrf
      <select class="select" name="tipo" aria-label="Tipo de CFDI">
        <option value="emitidos">Emitidos</option>
        <option value="recibidos">Recibidos</option>
        <option value="ambos">Ambos</option>
      </select>

      {{-- Nombres from/to para empatar con SatDescargaController::requestList --}}
      <input class="input" type="date" name="from" aria-label="Desde" required>
      <input class="input" type="date" name="to" aria-label="Hasta" required>

      {{-- RFC único (controlado por ahora por backend) --}}
      <select class="select" name="rfc" id="satRfc" aria-label="RFC" required
              data-tip="Selecciona el RFC para esta solicitud">
        <option value="">RFC...</option>
        @foreach($credList as $c)
          @php
            $rf = strtoupper($c['rfc'] ?? ($c->rfc ?? ''));
            $alias = $c['razon_social'] ?? ($c->razon_social ?? '');
          @endphp
          <option value="{{ $rf }}">
            {{ $rf }} @if($alias) · {{ $alias }} @endif
          </option>
        @endforeach
      </select>

      <button class="btn primary" type="submit" data-tip="Crear solicitud">
        <span aria-hidden="true">⤵️</span><span>Solicitar</span>
      </button>
      <button type="button" class="btn" id="btnSatVerify" data-tip="Verificar estado de solicitudes">
        <span aria-hidden="true">🔄</span><span>Verificar</span>
      </button>
      <a href="{{ $rtVault }}" class="btn" data-tip="Ir a Bóveda Fiscal">
        <span aria-hidden="true">💼</span><span>Bóveda</span>
      </a>
    </form>

    @if(!$isProPlan)
      <p class="text-muted" style="margin-top:6px">
        En el plan FREE puedes solicitar hasta <b>1 mes de rango</b> por ejecución
        y un volumen limitado de solicitudes simultáneas.
      </p>
    @endif
  </div>

  {{-- 3) AUTOMATIZADAS --}}
  <div class="sat-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px">
      <div>
        <div class="section-title">Automatizadas</div>
        <div class="section-sub">Programación de descargas periódicas por RFC</div>
      </div>
      @if(!$isProPlan)
        <div class="badge-plan-lock">Solo PRO</div>
      @else
        <div class="badge-plan-ok">Incluido en tu plan PRO</div>
      @endif
    </div>

    <div class="auto-grid {{ !$isProPlan ? 'is-locked' : '' }}">
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">⏱</span><span>Diario</span>
      </button>
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">📅</span><span>Semanal</span>
      </button>
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">🗓</span><span>Mensual</span>
      </button>
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">⚙️</span><span>Por rango</span>
      </button>
    </div>

    <p class="text-muted" style="margin-top:8px">
      @if(!$isProPlan)
        Estas opciones se activan al contratar el plan PRO. Cada ejecución automática consume solicitudes de tu cuota contratada.
      @else
        Tus automatizaciones están activas. Cada ejecución automática consumirá solicitudes de tu cuota contratada de descargas SAT.
      @endif
    </p>
  </div>

  {{-- 4) GRÁFICAS --}}
  <div class="sat-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
      <div>
        <div class="section-title">Tendencias</div>
        <div class="section-sub">Movimientos recientes de CFDI (últimos 6 meses)</div>
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
      Las series se calculan con tus CFDI reales (o, en su defecto, con el historial de descargas SAT).
    </p>
  </div>

  {{-- 5) GUÍAS RÁPIDAS --}}
  <div class="sat-card">
    <div style="margin-bottom:8px">
      <div class="section-title">Guías rápidas</div>
      <div class="section-sub">Flujos frecuentes de trabajo</div>
    </div>
    <div class="pills-row">
      <button type="button" class="pill primary" data-open="add-rfc" data-tip="Registrar un nuevo RFC / CSD">
        <span aria-hidden="true">➕</span><span>Agregar RFC</span>
      </button>
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

  {{-- 7) LISTADO DE DESCARGAS SAT (mejorado) --}}
  <div class="sat-card">
    <div class="sat-dl-head">
      <div class="sat-dl-title">
        <h3>Listado de descargas SAT</h3>
        <p>Histórico de solicitudes y paquetes generados</p>
      </div>
      <div class="sat-dl-filters">
        <input id="satDlSearch" type="text" placeholder="Buscar por ID o RFC">
        <select id="satDlTipo">
          <option value="">Todos los tipos</option>
          <option value="emitidos">Emitidos</option>
          <option value="recibidos">Recibidos</option>
        </select>
        <select id="satDlStatus">
          <option value="">Todos los estados</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="done">Listos</option>
        </select>
      </div>
    </div>

    <div class="sat-dl-table-wrap">
      <table class="sat-dl-table">
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
        <tbody id="satDlBody"></tbody>
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

{{-- MODAL: AGREGAR RFC / CSD --}}
<div class="sat-modal-backdrop" id="modalRfc">
  <div class="sat-modal">
    <div class="sat-modal-header">
      <div>
        <div class="sat-modal-kicker">Conexiones SAT · CSD</div>
        <div class="sat-modal-title">Agregar RFC</div>
        <p class="sat-modal-sub">
          Registra un nuevo RFC y, si quieres, sube el CSD para empezar a descargar CFDI.
        </p>
      </div>
      <button type="button"
              class="sat-modal-close"
              data-close="modal-rfc"
              aria-label="Cerrar">
        ✕
      </button>
    </div>

    <form id="formRfc">
      @csrf
      <div class="sat-modal-body">
        <div class="sat-field">
          <div class="sat-field-label">RFC</div>
          <input class="input"
                 type="text"
                 name="rfc"
                 maxlength="13"
                 placeholder="AAA010101AAA"
                 required>
        </div>

        <div class="sat-field">
          <div class="sat-field-label">Nombre o razón social (alias)</div>
          <input class="input"
                 type="text"
                 name="alias"
                 maxlength="190"
                 placeholder="Razón social para identificar el RFC">
        </div>

        <hr class="sat-modal-sep">

        <div class="sat-field sat-field-inline">
          <div>
            <div class="sat-field-label">Certificado (.cer)</div>
            <input class="input"
                   type="file"
                   name="cer"
                   accept=".cer">
          </div>
          <div>
            <div class="sat-field-label">Llave privada (.key)</div>
            <input class="input"
                   type="file"
                   name="key"
                   accept=".key">
          </div>
        </div>

        <div class="sat-field">
          <div class="sat-field-label">Contraseña de la llave</div>
          <input class="input"
                 type="password"
                 name="key_password"
                 autocomplete="new-password"
                 placeholder="••••••••">
        </div>

        <p class="sat-modal-note">
          Si no adjuntas <b>.cer</b> y <b>.key</b>, sólo se registrará el RFC.  
          Si adjuntas archivos y contraseña, se validará el <b>CSD</b> contra el SAT.
        </p>
      </div>

      <div class="sat-modal-footer">
        <button type="button" class="btn" data-close="modal-rfc">Cancelar</button>
        <button type="submit" class="btn primary">Guardar</button>
      </div>
    </form>
  </div>
</div>


@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const SAT_ROUTES = {
  charts: @json($rtCharts),
  csdStore: @json($rtCsdStore),
  rfcReg: @json($rtRfcReg),
  verify: @json($rtVerify),
  download: @json($rtPkgPost),
};
const SAT_ZIP_PATTERN   = @json($rtZipGet);
const SAT_DOWNLOAD_ROWS = @json($rowsInit);
const SAT_CSRF = '{{ csrf_token() }}';
</script>
<script>
/* ===== Cambiar DEMO/PROD con el botón de modo ===== */
(() => {
  const badge = document.getElementById('badgeMode');
  if (!badge || !badge.dataset.url) return;
  badge.addEventListener('click', async () => {
    const url = badge.dataset.url;
    badge.disabled = true;
    try{
      const res = await fetch(url, {
        method:'POST',
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':'{{ csrf_token() }}'
        }
      });
      if(!res.ok) throw new Error('HTTP '+res.status);
      location.reload();
    }catch(e){
      console.error(e);
      alert('No se pudo cambiar el modo');
      badge.disabled = false;
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

/* ===== Verificar solicitudes (POST) ===== */
(() => {
  const btn = document.getElementById('btnSatVerify');
  if(!btn || !SAT_ROUTES.verify) return;
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    try{
      const r = await fetch(SAT_ROUTES.verify, {
        method:'POST',
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':SAT_CSRF
        }
      });
      const j = await r.json();
      alert(`Pendientes: ${j.pending ?? 0} · Listos: ${j.ready ?? 0}`);
      location.reload();
    }catch(e){
      console.error(e);
      alert('No se pudo verificar');
    }finally{
      btn.disabled = false;
    }
  });
})();

/* ===== Regla FREE: ≤ 1 mes ===== */
(() => {
  const planIsPro = @json($isProPlan);
  const f = document.getElementById('reqForm');
  if(!f || planIsPro) return;
  f.addEventListener('submit', ev=>{
    const d = new FormData(f);
    const a = new Date(d.get('from'));
    const b = new Date(d.get('to'));
    if(isNaN(+a) || isNaN(+b)) return;
    if((b - a) > 32*24*3600*1000){
      ev.preventDefault();
      alert('En FREE sólo puedes solicitar hasta 1 mes.');
    }
  });
})();

/* ===== Modal RFC (abrir / cerrar / submit) ===== */
(() => {
  const modal = document.getElementById('modalRfc');
  const form  = document.getElementById('formRfc');
  if(!modal || !form) return;

  const open  = () => modal.classList.add('is-open');
  const close = () => modal.classList.remove('is-open');

  document.addEventListener('click',(ev)=>{
    const openBtn  = ev.target.closest('[data-open="add-rfc"]');
    const closeBtn = ev.target.closest('[data-close="modal-rfc"]');
    if(openBtn){
      ev.preventDefault();
      open();
    }
    if(closeBtn || ev.target === modal){
      ev.preventDefault();
      close();
    }
  });

  window.addEventListener('sat-open-add-rfc', open);

  form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    if(submitBtn) submitBtn.disabled = true;

    const fd = new FormData(form);
    const hasCer = fd.get('cer') instanceof File && fd.get('cer').name;
    const hasKey = fd.get('key') instanceof File && fd.get('key').name;
    const pwd    = (fd.get('key_password') || '').toString().trim();
    const useCsd = (hasCer || hasKey || pwd !== '');
    const url    = useCsd ? SAT_ROUTES.csdStore : SAT_ROUTES.rfcReg;

    if(!url || url === '#'){
      alert('Ruta de guardado de RFC no configurada.');
      if(submitBtn) submitBtn.disabled = false;
      return;
    }

    try{
      const res = await fetch(url, {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body: fd
      });
      let data = {};
      try{ data = await res.json(); }catch(_){}
      if(!res.ok || (data.ok === false)){
        const msg = data.msg || 'No se pudo guardar el RFC / CSD';
        alert(msg);
        if(submitBtn) submitBtn.disabled = false;
        return;
      }
      close();
      location.reload();
    }catch(e){
      console.error(e);
      alert('Error enviando datos');
    }finally{
      if(submitBtn) submitBtn.disabled = false;
    }
  });
})();

/* ===== Charts con datos reales (endpoint /sat/charts) ===== */
(() => {
  if(!SAT_ROUTES.charts) return;

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

  const chartA = mk('chartA','Importe total');
  const chartB = mk('chartB','# CFDI');
  if(!chartA || !chartB) return;

  const loadScope = async (scope) => {
    try{
      const url = SAT_ROUTES.charts + '?scope=' + encodeURIComponent(scope || 'emitidos');
      const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!res.ok) throw new Error('HTTP '+res.status);
      const j = await res.json();
      const labels  = j.labels || [];
      const series  = j.series || {};
      const amounts = series.amounts || [];
      const counts  = series.counts  || [];

      chartA.data.labels = labels;
      chartB.data.labels = labels;

      chartA.data.datasets[0].label = series.label_amount || 'Importe total';
      chartB.data.datasets[0].label = series.label_count  || '# CFDI';

      chartA.data.datasets[0].data = amounts;
      chartB.data.datasets[0].data = counts;

      chartA.update();
      chartB.update();
    }catch(e){
      console.error(e);
    }
  };

  loadScope('emitidos');

  document.querySelectorAll('.tab').forEach(t=>{
    t.addEventListener('click',()=>{
      document.querySelectorAll('.tab').forEach(x=>x.classList.remove('is-active'));
      t.classList.add('is-active');
      loadScope(t.dataset.scope || 'emitidos');
    });
  });
})();

/* ===== LISTADO DESCARGAS: filtros + descarga ZIP ===== */
(() => {
  const rows = Array.isArray(SAT_DOWNLOAD_ROWS) ? SAT_DOWNLOAD_ROWS : [];
  const body = document.getElementById('satDlBody');
  const qInput = document.getElementById('satDlSearch');
  const tipoSel = document.getElementById('satDlTipo');
  const statusSel = document.getElementById('satDlStatus');

  function normalizeStatus(s){
    s = (s || '').toString().toLowerCase();
    if (['ready','done','listo'].includes(s)) return 'done';
    return s;
  }

  function render(){
    if (!body) return;
    const q = (qInput?.value || '').toLowerCase();
    const ftipo = (tipoSel?.value || '').toLowerCase();
    const fstat = (statusSel?.value || '').toLowerCase();

    body.innerHTML = '';

    if (!rows.length){
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 6;
      td.className = 'empty';
      td.textContent = 'Aún no has generado solicitudes de descarga.';
      tr.appendChild(td);
      body.appendChild(tr);
      return;
    }

    rows.forEach(r => {
      const id = r.dlid || r.id || '';
      const rfc = (r.rfc || '').toString();
      const tipo = (r.tipo || '').toString().toLowerCase();
      const statusRaw = (r.estado || r.status || '').toString();
      const status = normalizeStatus(statusRaw);

      if (q && !id.toLowerCase().includes(q) && !rfc.toLowerCase().includes(q)) {
        return;
      }
      if (ftipo && tipo !== ftipo) return;
      if (fstat && status !== fstat) return;

      const tr = document.createElement('tr');

      const periodo = (r.desde || '') && (r.hasta || '')
        ? `${r.desde} → ${r.hasta}`
        : '';

      tr.innerHTML = `
        <td class="mono">${id}</td>
        <td>${tipo ? ('• ' + tipo.charAt(0).toUpperCase() + tipo.slice(1)) : ''}</td>
        <td>${periodo}</td>
        <td><span class="sat-badge-status ${status}">${status || ''}</span></td>
        <td class="mono">${r.package_id || ''}</td>
        <td>
          <button
            class="sat-dl-btn-download"
            data-id="${id}"
            data-status="${status}"
            title="Descargar ZIP"
          >
            ⬇
          </button>
        </td>
      `;
      body.appendChild(tr);
    });
  }

  if (qInput) qInput.addEventListener('input', render);
  if (tipoSel) tipoSel.addEventListener('change', render);
  if (statusSel) statusSel.addEventListener('change', render);

  if (body) {
    body.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('.sat-dl-btn-download');
      if (!btn) return;

      const id     = btn.dataset.id;
      const status = (btn.dataset.status || '').toLowerCase();

      if (!id) return;

      // 1) Si ya está "done", abrimos directamente el ZIP
      if (status === 'done' && SAT_ZIP_PATTERN && SAT_ZIP_PATTERN !== '#') {
        const zipUrl = SAT_ZIP_PATTERN.replace('__ID__', id);
        window.location.href = zipUrl;
        return;
      }

      // 2) Si no está listo, llamamos al endpoint /sat/download
      if (!SAT_ROUTES.download || SAT_ROUTES.download === '#') return;

      btn.disabled = true;

      try{
        const fd = new FormData();
        // IMPORTANTE: el backend espera "download_id"
        fd.append('download_id', id);

        const res = await fetch(SAT_ROUTES.download, {
          method:'POST',
          headers:{
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': SAT_CSRF,
          },
          body: fd,
        });

        const ct = res.headers.get('content-type') || '';
        let json = null;
        if (ct.includes('application/json')) {
          json = await res.json().catch(()=>null);
        }

        if (!res.ok) {
          alert(json?.msg || 'No se pudo preparar el ZIP.');
          return;
        }

        if (json && json.zip_url) {
          window.location.href = json.zip_url;
        } else {
          location.reload();
        }
      }catch(e){
        console.error(e);
        alert('Error de conexión al descargar.');
      }finally{
        btn.disabled = false;
      }
    });
  }

  render();
})();

</script>
@endpush
